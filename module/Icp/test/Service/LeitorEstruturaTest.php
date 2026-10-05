<?php

declare(strict_types=1);

namespace IcpTest\Service;

use Icp\Enum\Situacao;
use Icp\Exception\EstruturaInvalidaException;
use Icp\Service\EstruturaImportada;
use Icp\Service\LeitorEstrutura;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function json_encode;
use function str_repeat;

final class LeitorEstruturaTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../Fixture/structure-minima.json';

    private function lerFixture(): EstruturaImportada
    {
        return (new LeitorEstrutura())->ler((string) file_get_contents(self::FIXTURE));
    }

    public function testMapeiaNiveisDoItiParaAcAcN2EAr(): void
    {
        $estrutura = $this->lerFixture();

        self::assertSame([10, 20], array_keys($estrutura->acs));
        self::assertSame([100, 101, 200], array_keys($estrutura->acN2s));
        self::assertSame(10, $estrutura->acN2s[100]['acItiId']);
        self::assertSame(20, $estrutura->acN2s[200]['acItiId']);
    }

    public function testArRepetidaViraUmRegistroComTodosOsVinculos(): void
    {
        $estrutura = $this->lerFixture();

        self::assertSame('AR COMPARTILHADA', $estrutura->ars[1000]['nome']);
        // Aparece 2x sob a 100 (duplicada no arquivo) e 1x sob a 200: dois vínculos únicos.
        self::assertSame([100, 200], array_keys($estrutura->ars[1000]['vinculos']));
    }

    /**
     * Regressão (achado B1 da revisão final do Codex): a situação de uma AR que
     * difere entre as AC N2 era descartada; a primeira valia para todas.
     */
    public function testSituacaoFicaPorVinculoEAGeralEhCredenciadoSeAlgumForCredenciado(): void
    {
        $estrutura = $this->lerFixture();

        self::assertSame(
            [100 => Situacao::Credenciado, 200 => Situacao::EmCredenciamento],
            $estrutura->ars[1000]['vinculos']
        );
        self::assertSame(Situacao::Credenciado, $estrutura->ars[1000]['situacao']);
        self::assertContains(
            '1 AR têm situação diferente conforme a AC N2; a situação foi registrada em cada vínculo.',
            $estrutura->avisos
        );
    }

    public function testMesmaArRepetidaSobAMesmaAcN2ComOutraSituacaoMantemAPrimeira(): void
    {
        $json = json_encode([
            'tipo'                 => 'ac-root',
            'entidades_vinculadas' => [[
                'tipo' => 'ac-1', 'id' => 1, 'situacao' => 4002, 'nome' => 'AC',
                'entidades_vinculadas' => [[
                    'tipo' => 'ac-2', 'id' => 2, 'situacao' => 4002, 'nome' => 'AC N2',
                    'entidades_vinculadas' => [
                        ['tipo' => 'ar', 'id' => 3, 'situacao' => 4001, 'nome' => 'AR'],
                        ['tipo' => 'ar', 'id' => 3, 'situacao' => 4002, 'nome' => 'AR'],
                    ],
                ]],
            ]],
        ]);

        $estrutura = (new LeitorEstrutura())->ler((string) $json);

        self::assertSame([2 => Situacao::EmCredenciamento], $estrutura->ars[3]['vinculos']);
        self::assertSame(Situacao::EmCredenciamento, $estrutura->ars[3]['situacao']);
        self::assertContains(
            'AR "AR" aparece mais de uma vez sob a mesma AC N2 com situações diferentes; mantida a primeira.',
            $estrutura->avisos
        );
    }

    public function testVinculoDiretoComAc1EhIgnoradoEArSemAcN2NaoEhImportada(): void
    {
        $estrutura = $this->lerFixture();

        self::assertArrayNotHasKey(1002, $estrutura->ars);
        self::assertStringContainsString('2 vínculo(s) de AR direto com AC 1º nível', $estrutura->avisos[0]);
        self::assertContains(
            'AR "AR SO NO PRIMEIRO NIVEL" está ligada apenas a uma AC 1º nível e não foi importada.',
            $estrutura->avisos
        );
    }

    public function testConverteSituacaoERemoveEspacosDasPontasDoNome(): void
    {
        $estrutura = $this->lerFixture();

        self::assertSame(Situacao::EmCredenciamento, $estrutura->acN2s[101]['situacao']);
        self::assertSame(Situacao::EmCredenciamento, $estrutura->ars[1001]['situacao']);
        self::assertSame('AR NOVA', $estrutura->ars[1001]['nome']);
    }

    public function testSituacaoDesconhecidaViraCredenciadoComAviso(): void
    {
        $estrutura = $this->lerFixture();

        self::assertSame(Situacao::Credenciado, $estrutura->ars[1003]['situacao']);
        self::assertContains('"AR SITUACAO ESTRANHA" tem situação desconhecida; registrado como credenciado.', $estrutura->avisos);
    }

    public function testRegistroSemNomeEhIgnorado(): void
    {
        $estrutura = $this->lerFixture();

        self::assertArrayNotHasKey(1004, $estrutura->ars);
        // Vínculos restantes: 1000 (2) + 1001 (1) + 1003 (1).
        self::assertSame(4, $estrutura->totalVinculos());
    }

    public function testNomeMaiorQueOLimiteEhTruncado(): void
    {
        $json = json_encode([
            'tipo'                 => 'ac-root',
            'entidades_vinculadas' => [
                ['tipo' => 'ac-1', 'id' => 1, 'situacao' => 4002, 'nome' => str_repeat('A', 200)],
            ],
        ]);

        $estrutura = (new LeitorEstrutura())->ler((string) $json);

        self::assertSame(150, mb_strlen($estrutura->acs[1]['nome']));
    }

    /**
     * Regressão (revisão do Codex): ids fora da faixa do INT do MySQL passavam
     * pela leitura e derrubavam a importação com erro 500 na gravação.
     */
    public function testIdForaDaFaixaDoBancoEhIgnoradoComAviso(): void
    {
        $json = json_encode([
            'tipo'                 => 'ac-root',
            'entidades_vinculadas' => [
                ['tipo' => 'ac-1', 'id' => 1, 'situacao' => 4002, 'nome' => 'AC VALIDA'],
                ['tipo' => 'ac-1', 'id' => 2147483648, 'situacao' => 4002, 'nome' => 'AC ID GRANDE'],
                ['tipo' => 'ac-1', 'id' => 0, 'situacao' => 4002, 'nome' => 'AC ID ZERO'],
            ],
        ]);

        $estrutura = (new LeitorEstrutura())->ler((string) $json);

        self::assertSame([1], array_keys($estrutura->acs));
        self::assertContains('Registro sem id, nome ou tipo válido sob "AC RAIZ" foi ignorado.', $estrutura->avisos);
    }

    public function testHtmlNoLugarDoJsonGeraMensagemExplicativa(): void
    {
        $this->expectException(EstruturaInvalidaException::class);
        $this->expectExceptionMessage('assets/jsons/structure.json');

        (new LeitorEstrutura())->ler("<!doctype html>\n<html lang=\"pt-br\"><app-root></app-root></html>");
    }

    public function testJsonInvalido(): void
    {
        $this->expectException(EstruturaInvalidaException::class);
        $this->expectExceptionMessage('não contém um JSON válido');

        (new LeitorEstrutura())->ler('{"tipo": "ac-root",');
    }

    public function testRaizComOutroFormato(): void
    {
        $this->expectException(EstruturaInvalidaException::class);
        $this->expectExceptionMessage('ac-root');

        (new LeitorEstrutura())->ler('[{"tipo": "ac-1", "id": 1, "nome": "X"}]');
    }

    public function testArquivoSemNenhumaAc(): void
    {
        $this->expectException(EstruturaInvalidaException::class);
        $this->expectExceptionMessage('Nenhuma AC');

        (new LeitorEstrutura())->ler('{"tipo": "ac-root", "entidades_vinculadas": []}');
    }

    public function testAceitaBomUtf8NoInicio(): void
    {
        $estrutura = (new LeitorEstrutura())->ler("\xEF\xBB\xBF" . (string) file_get_contents(self::FIXTURE));

        self::assertCount(2, $estrutura->acs);
    }
}
