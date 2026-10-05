<?php

declare(strict_types=1);

namespace Icp\Service;

use Icp\Enum\Situacao;
use Icp\Exception\EstruturaInvalidaException;
use Icp\Form\EspecificacaoCampos;
use JsonException;

use function array_key_exists;
use function array_map;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function ltrim;
use function mb_strlen;
use function mb_substr;
use function sprintf;
use function str_starts_with;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Lê o conteúdo do structure.json do ITI e devolve AC, AC N2 e AR normalizadas.
 *
 * Formato esperado (árvore aninhada em "entidades_vinculadas"):
 *   ac-root → ac-1 (AC) → ac-2 (AC N2) → ar (AR)
 *
 * Particularidades do arquivo real tratadas aqui:
 * - a mesma AR aparece sob várias AC N2 (vínculos N:N são unificados pelo id),
 *   às vezes com situação diferente em cada uma; a situação fica por vínculo;
 * - algumas AR aparecem direto sob uma AC 1º nível; esse vínculo não existe no
 *   modelo (AR → AC N2) e é ignorado, com aviso;
 * - AR sem nenhuma AC N2 após a leitura não são importadas, com aviso.
 *
 * Não acessa banco nem arquivos: recebe a string JSON.
 */
final class LeitorEstrutura
{
    private const PROFUNDIDADE_MAXIMA = 32;
    /** Maior valor da coluna iti_id (INT com sinal no MySQL). */
    private const ID_MAXIMO = 2147483647;
    private const MAXIMO_AVISOS_DETALHADOS = 20;

    /** @var array<int, array{nome: string, situacao: Situacao}> */
    private array $acs = [];
    /** @var array<int, array{nome: string, situacao: Situacao, acItiId: int}> */
    private array $acN2s = [];
    /** @var array<int, array{nome: string, situacao: Situacao, vinculos: array<int, Situacao>}> */
    private array $ars = [];
    /** @var array<string, list<string>> avisos agrupados por categoria */
    private array $avisos = [];
    private int $vinculosDiretosAcAr = 0;

    public function ler(string $json): EstruturaImportada
    {
        $this->acs   = $this->acN2s = $this->ars = $this->avisos = [];
        $this->vinculosDiretosAcAr = 0;

        $raiz = $this->decodificar($json);

        if (($raiz['tipo'] ?? null) !== 'ac-root' || ! is_array($raiz['entidades_vinculadas'] ?? null)) {
            throw new EstruturaInvalidaException(
                'Formato inesperado: o nó raiz deve ser a "AC RAIZ" (tipo "ac-root") '
                . 'com a lista "entidades_vinculadas".'
            );
        }

        foreach ($raiz['entidades_vinculadas'] as $filho) {
            $no = $this->validarNo($filho, 'AC RAIZ');
            if ($no === null) {
                continue;
            }

            if ($no['tipo'] !== 'ac-1') {
                $this->avisar('tipo', sprintf('Entidade "%s" (tipo "%s") sob a AC RAIZ foi ignorada.', $no['nome'], $no['tipo']));
                continue;
            }

            $this->lerAc($no, $filho);
        }

        if ($this->acs === []) {
            throw new EstruturaInvalidaException('Nenhuma AC de 1º nível foi encontrada no arquivo.');
        }

        return new EstruturaImportada($this->acs, $this->acN2s, $this->arsComVinculo(), $this->consolidarAvisos());
    }

    /** @return array<string, mixed> */
    private function decodificar(string $json): array
    {
        $conteudo = ltrim($json, "\xEF\xBB\xBF \t\r\n");

        if (str_starts_with($conteudo, '<')) {
            throw new EstruturaInvalidaException(
                'O arquivo contém HTML, não JSON. O endereço /assets/structure.json devolve a página do site; '
                . 'o arquivo de dados fica em https://estrutura.iti.gov.br/assets/jsons/structure.json.'
            );
        }

        try {
            $dados = json_decode($conteudo, true, self::PROFUNDIDADE_MAXIMA, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new EstruturaInvalidaException('O arquivo não contém um JSON válido: ' . $e->getMessage(), 0, $e);
        }

        if (! is_array($dados)) {
            throw new EstruturaInvalidaException('O JSON deve ser um objeto com a AC RAIZ.');
        }

        return $dados;
    }

    /**
     * @param array{id: int, nome: string, tipo: string, situacao: Situacao} $no
     * @param array<string, mixed> $bruto
     */
    private function lerAc(array $no, array $bruto): void
    {
        $this->acs[$no['id']] ??= ['nome' => $no['nome'], 'situacao' => $no['situacao']];

        foreach ($this->filhos($bruto) as $filho) {
            $filhoNo = $this->validarNo($filho, $no['nome']);
            if ($filhoNo === null) {
                continue;
            }

            if ($filhoNo['tipo'] === 'ac-2') {
                $this->lerAcN2($filhoNo, $filho, $no['id']);
            } elseif ($filhoNo['tipo'] === 'ar') {
                // Vínculo AR → AC 1º nível não existe no modelo; a AR só entra se tiver AC N2.
                $this->registrarAr($filhoNo, null);
                $this->vinculosDiretosAcAr++;
            } else {
                $this->avisar('tipo', sprintf('Entidade "%s" (tipo "%s") sob "%s" foi ignorada.', $filhoNo['nome'], $filhoNo['tipo'], $no['nome']));
            }
        }
    }

    /**
     * @param array{id: int, nome: string, tipo: string, situacao: Situacao} $no
     * @param array<string, mixed> $bruto
     */
    private function lerAcN2(array $no, array $bruto, int $acItiId): void
    {
        if (isset($this->acN2s[$no['id']]) && $this->acN2s[$no['id']]['acItiId'] !== $acItiId) {
            $this->avisar('duplicidade', sprintf('AC N2 "%s" aparece sob mais de uma AC; mantida a primeira.', $no['nome']));
        } else {
            $this->acN2s[$no['id']] = ['nome' => $no['nome'], 'situacao' => $no['situacao'], 'acItiId' => $acItiId];
        }

        foreach ($this->filhos($bruto) as $filho) {
            $filhoNo = $this->validarNo($filho, $no['nome']);
            if ($filhoNo === null) {
                continue;
            }

            if ($filhoNo['tipo'] === 'ar') {
                $this->registrarAr($filhoNo, $no['id']);
            } else {
                $this->avisar('tipo', sprintf('Entidade "%s" (tipo "%s") sob "%s" foi ignorada.', $filhoNo['nome'], $filhoNo['tipo'], $no['nome']));
            }
        }
    }

    /** @param array{id: int, nome: string, tipo: string, situacao: Situacao} $no */
    private function registrarAr(array $no, ?int $acN2ItiId): void
    {
        if (! isset($this->ars[$no['id']])) {
            $this->ars[$no['id']] = ['nome' => $no['nome'], 'situacao' => $no['situacao'], 'vinculos' => []];
        } elseif ($this->ars[$no['id']]['nome'] !== $no['nome']) {
            $this->avisar('duplicidade', sprintf(
                'AR id %d aparece com nomes diferentes ("%s" e "%s"); mantido o primeiro.',
                $no['id'],
                $this->ars[$no['id']]['nome'],
                $no['nome']
            ));
        }

        if ($acN2ItiId === null) {
            return;
        }

        $vinculos = &$this->ars[$no['id']]['vinculos'];
        if (! isset($vinculos[$acN2ItiId])) {
            $vinculos[$acN2ItiId] = $no['situacao'];
        } elseif ($vinculos[$acN2ItiId] !== $no['situacao']) {
            $this->avisar('duplicidade', sprintf(
                'AR "%s" aparece mais de uma vez sob a mesma AC N2 com situações diferentes; mantida a primeira.',
                $no['nome']
            ));
        }
    }

    /** @return array<int, array{nome: string, situacao: Situacao, vinculos: array<int, Situacao>}> */
    private function arsComVinculo(): array
    {
        $resultado = [];
        $comSituacaoMista = 0;

        foreach ($this->ars as $itiId => $ar) {
            if ($ar['vinculos'] === []) {
                $this->avisar('sem-vinculo', sprintf('AR "%s" está ligada apenas a uma AC 1º nível e não foi importada.', $ar['nome']));
                continue;
            }

            if (count(array_unique(array_map(static fn (Situacao $s): int => $s->value, $ar['vinculos']))) > 1) {
                $comSituacaoMista++;
            }

            $resultado[$itiId] = [
                'nome'     => $ar['nome'],
                'situacao' => self::situacaoGeral($ar['vinculos']),
                'vinculos' => $ar['vinculos'],
            ];
        }

        if ($comSituacaoMista > 0) {
            $this->avisar('situacao-vinculo', sprintf(
                '%d AR têm situação diferente conforme a AC N2; a situação foi registrada em cada vínculo.',
                $comSituacaoMista
            ));
        }

        return $resultado;
    }

    /**
     * Mesma regra de Ar::recalcularSituacao(): Credenciado se algum vínculo
     * estiver credenciado; senão, Em credenciamento.
     *
     * @param array<int, Situacao> $vinculos
     */
    private static function situacaoGeral(array $vinculos): Situacao
    {
        return in_array(Situacao::Credenciado, $vinculos, true) ? Situacao::Credenciado : Situacao::EmCredenciamento;
    }

    /**
     * Valida os campos obrigatórios de um nó; devolve null (com aviso) se inválido.
     *
     * @return array{id: int, nome: string, tipo: string, situacao: Situacao}|null
     */
    private function validarNo(mixed $no, string $pai): ?array
    {
        if (
            ! is_array($no) || ! is_int($no['id'] ?? null) || $no['id'] < 1 || $no['id'] > self::ID_MAXIMO
            || ! is_string($no['nome'] ?? null)
            || ! is_string($no['tipo'] ?? null) || trim($no['nome']) === ''
        ) {
            $this->avisar('invalido', sprintf('Registro sem id, nome ou tipo válido sob "%s" foi ignorado.', $pai));
            return null;
        }

        $nome = trim($no['nome']);
        if (mb_strlen($nome) > EspecificacaoCampos::TAMANHO_NOME) {
            $this->avisar('nome', sprintf('Nome "%s" foi truncado para %d caracteres.', $nome, EspecificacaoCampos::TAMANHO_NOME));
            $nome = mb_substr($nome, 0, EspecificacaoCampos::TAMANHO_NOME);
        }

        $situacao = is_int($no['situacao'] ?? null) ? Situacao::tryFrom($no['situacao']) : null;
        if ($situacao === null) {
            $this->avisar('situacao', sprintf('"%s" tem situação desconhecida; registrado como credenciado.', $nome));
            $situacao = Situacao::Credenciado;
        }

        return ['id' => $no['id'], 'nome' => $nome, 'tipo' => $no['tipo'], 'situacao' => $situacao];
    }

    /**
     * @param array<string, mixed> $no
     * @return list<mixed>
     */
    private function filhos(array $no): array
    {
        if (! array_key_exists('entidades_vinculadas', $no) || $no['entidades_vinculadas'] === null) {
            return [];
        }

        return is_array($no['entidades_vinculadas']) ? array_values($no['entidades_vinculadas']) : [];
    }

    private function avisar(string $categoria, string $mensagem): void
    {
        if (! isset($this->avisos[$categoria]) || ! in_array($mensagem, $this->avisos[$categoria], true)) {
            $this->avisos[$categoria][] = $mensagem;
        }
    }

    /** @return list<string> */
    private function consolidarAvisos(): array
    {
        $avisos = [];

        if ($this->vinculosDiretosAcAr > 0) {
            $avisos[] = sprintf(
                '%d vínculo(s) de AR direto com AC 1º nível foram ignorados (o modelo vincula AR somente a AC N2).',
                $this->vinculosDiretosAcAr
            );
        }

        foreach ($this->avisos as $mensagens) {
            $total = count($mensagens);
            foreach (array_slice($mensagens, 0, self::MAXIMO_AVISOS_DETALHADOS) as $mensagem) {
                $avisos[] = $mensagem;
            }
            if ($total > self::MAXIMO_AVISOS_DETALHADOS) {
                $avisos[] = sprintf('… e mais %d aviso(s) semelhantes.', $total - self::MAXIMO_AVISOS_DETALHADOS);
            }
        }

        return $avisos;
    }
}
