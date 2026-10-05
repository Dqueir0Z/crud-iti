<?php

declare(strict_types=1);

namespace Icp\Controller;

use Icp\Entity\Ac;
use Icp\Entity\AcN2;
use Icp\Entity\Ar;
use Icp\Enum\Situacao;
use Icp\Repository\AcN2Repository;
use Icp\Repository\AcRepository;
use Icp\Repository\ArRepository;
use Laminas\View\Model\ViewModel;

/**
 * Árvore AC → AC N2 → AR, simulando a página estrutura.iti.gov.br.
 * Usa três consultas (sem N+1) e hidratação em array para as ARs.
 */
final class EstruturaController extends AbstractIcpController
{
    public function indexAction(): ViewModel
    {
        /** @var AcRepository $acs */
        $acs = $this->entityManager->getRepository(Ac::class);
        /** @var AcN2Repository $acN2s */
        $acN2s = $this->entityManager->getRepository(AcN2::class);
        /** @var ArRepository $ars */
        $ars = $this->entityManager->getRepository(Ar::class);

        $acN2sPorAc = [];
        foreach ($acN2s->findAllComAc() as $acN2) {
            $acN2sPorAc[$acN2->getAc()->getId()][] = $acN2;
        }

        $arsPorAcN2 = [];
        foreach ($ars->findVinculosParaArvore() as $vinculo) {
            $situacao = $vinculo['situacao'];
            $arsPorAcN2[$vinculo['acN2Id']][] = [
                'id'       => $vinculo['id'],
                'nome'     => $vinculo['nome'],
                'situacao' => $situacao instanceof Situacao ? $situacao : Situacao::from((int) $situacao),
            ];
        }

        return new ViewModel([
            'acs'        => $acs->findAllOrdenadas(),
            'acN2sPorAc' => $acN2sPorAc,
            'arsPorAcN2' => $arsPorAcN2,
            'totalAr'    => $ars->count([]),
        ]);
    }
}
