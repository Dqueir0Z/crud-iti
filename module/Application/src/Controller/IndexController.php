<?php

declare(strict_types=1);

namespace Application\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Icp\Entity\Ac;
use Icp\Entity\AcN2;
use Icp\Entity\Ar;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

/**
 * Painel inicial com os totais de cada entidade.
 */
class IndexController extends AbstractActionController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function indexAction(): ViewModel
    {
        $totalVinculos = (int) $this->entityManager
            ->createQuery('SELECT COUNT(n.id) FROM ' . Ar::class . ' r JOIN r.acN2s n')
            ->getSingleScalarResult();

        return new ViewModel([
            'totalAc'       => $this->entityManager->getRepository(Ac::class)->count([]),
            'totalAcN2'     => $this->entityManager->getRepository(AcN2::class)->count([]),
            'totalAr'       => $this->entityManager->getRepository(Ar::class)->count([]),
            'totalVinculos' => $totalVinculos,
        ]);
    }
}
