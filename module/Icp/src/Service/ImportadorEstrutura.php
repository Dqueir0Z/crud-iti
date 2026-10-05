<?php

declare(strict_types=1);

namespace Icp\Service;

use Doctrine\ORM\EntityManagerInterface;
use Icp\Entity\Ac;
use Icp\Entity\AcN2;
use Icp\Entity\Ar;
use Icp\Enum\Situacao;
use Icp\Repository\AcN2Repository;
use Icp\Repository\AcRepository;
use Icp\Repository\ArRepository;

/**
 * Grava no banco a estrutura lida do structure.json.
 *
 * A importação é idempotente: os registros são casados pelo id do ITI
 * (coluna iti_id), então reenviar o mesmo arquivo atualiza em vez de duplicar.
 * Vínculos AR ↔ AC N2 presentes no arquivo são criados ou têm a situação
 * atualizada; vínculos ausentes do arquivo (inclusive os feitos manualmente)
 * são preservados. Tudo roda em uma única transação.
 */
final class ImportadorEstrutura
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LeitorEstrutura $leitor
    ) {
    }

    public function importarJson(string $json): ResultadoImportacao
    {
        return $this->importar($this->leitor->ler($json));
    }

    public function importar(EstruturaImportada $estrutura): ResultadoImportacao
    {
        return $this->entityManager->wrapInTransaction(function () use ($estrutura): ResultadoImportacao {
            $resultado = new ResultadoImportacao($estrutura->avisos);

            $acs   = $this->importarAcs($estrutura, $resultado);
            $acN2s = $this->importarAcN2s($estrutura, $acs, $resultado);
            $this->importarArs($estrutura, $acN2s, $resultado);

            $this->entityManager->flush();

            return $resultado;
        });
    }

    /** @return array<int, Ac> */
    private function importarAcs(EstruturaImportada $estrutura, ResultadoImportacao $resultado): array
    {
        /** @var AcRepository $repositorio */
        $repositorio = $this->entityManager->getRepository(Ac::class);
        $existentes  = $repositorio->indexarPorItiId();

        foreach ($estrutura->acs as $itiId => $dados) {
            $ac = $existentes[$itiId] ?? null;

            if ($ac === null) {
                $ac = new Ac($dados['nome'], $dados['situacao'], $itiId);
                $this->entityManager->persist($ac);
                $existentes[$itiId] = $ac;
                $resultado->contar('AC', 'criados');
                continue;
            }

            $alterado = $this->atualizarBasicos($ac, $dados['nome'], $dados['situacao']);
            $resultado->contar('AC', $alterado ? 'atualizados' : 'inalterados');
        }

        return $existentes;
    }

    /**
     * @param array<int, Ac> $acs
     * @return array<int, AcN2>
     */
    private function importarAcN2s(EstruturaImportada $estrutura, array $acs, ResultadoImportacao $resultado): array
    {
        /** @var AcN2Repository $repositorio */
        $repositorio = $this->entityManager->getRepository(AcN2::class);
        $existentes  = $repositorio->indexarPorItiId();

        foreach ($estrutura->acN2s as $itiId => $dados) {
            $ac   = $acs[$dados['acItiId']];
            $acN2 = $existentes[$itiId] ?? null;

            if ($acN2 === null) {
                $acN2 = new AcN2($dados['nome'], $ac, $dados['situacao'], $itiId);
                $this->entityManager->persist($acN2);
                $existentes[$itiId] = $acN2;
                $resultado->contar('AC N2', 'criados');
                continue;
            }

            $alterado = $this->atualizarBasicos($acN2, $dados['nome'], $dados['situacao']);
            if ($acN2->getAc() !== $ac) {
                $acN2->setAc($ac);
                $alterado = true;
            }
            $resultado->contar('AC N2', $alterado ? 'atualizados' : 'inalterados');
        }

        return $existentes;
    }

    /** @param array<int, AcN2> $acN2s */
    private function importarArs(EstruturaImportada $estrutura, array $acN2s, ResultadoImportacao $resultado): void
    {
        /** @var ArRepository $repositorio */
        $repositorio = $this->entityManager->getRepository(Ar::class);
        $existentes  = $repositorio->indexarPorItiIdComVinculos();

        foreach ($estrutura->ars as $itiId => $dados) {
            $ar       = $existentes[$itiId] ?? null;
            $nova     = $ar === null;
            $alterado = false;

            if ($nova) {
                $ar = new Ar($dados['nome'], $dados['situacao'], $itiId);
                $this->entityManager->persist($ar);
            } elseif ($ar->getNome() !== $dados['nome']) {
                $ar->setNome($dados['nome']);
                $alterado = true;
            }

            // A situação vem de cada vínculo; a da AR é recalculada sobre todos os
            // vínculos que ela tem no banco, inclusive os que não estão no arquivo.
            foreach ($dados['vinculos'] as $acN2ItiId => $situacao) {
                $acN2 = $acN2s[$acN2ItiId];

                if ($ar->vincularAcN2($acN2, $situacao)) {
                    $resultado->vinculosCriados++;
                    $alterado = true;
                } elseif ($ar->definirSituacaoDoVinculo($acN2, $situacao)) {
                    $resultado->vinculosAtualizados++;
                    $alterado = true;
                }
            }

            if ($ar->recalcularSituacao()) {
                $alterado = true;
            }

            $resultado->contar('AR', $nova ? 'criados' : ($alterado ? 'atualizados' : 'inalterados'));
        }
    }

    private function atualizarBasicos(Ac|AcN2 $entidade, string $nome, Situacao $situacao): bool
    {
        $alterado = false;

        if ($entidade->getNome() !== $nome) {
            $entidade->setNome($nome);
            $alterado = true;
        }

        if ($entidade->getSituacao() !== $situacao) {
            $entidade->setSituacao($situacao);
            $alterado = true;
        }

        return $alterado;
    }
}
