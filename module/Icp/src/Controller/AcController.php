<?php

declare(strict_types=1);

namespace Icp\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Icp\Entity\Ac;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class AcController extends AbstractActionController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function indexAction(): ViewModel
    {
        $acs = $this->entityManager
            ->getRepository(Ac::class)
            ->findAll();

        return new ViewModel([
            'acs' => $acs,
        ]);
    }

    public function createAction()
    {
        if ($this->getRequest()->isPost()) {
            $nome = trim(
                (string) $this->params()->fromPost('nome', '')
            );

            if ($nome !== '') {
                $ac = new Ac($nome);

                $this->entityManager->persist($ac);
                $this->entityManager->flush();

                return $this->redirect()->toRoute('ac');
            }
        }

        return new ViewModel();
    }

    public function editAction()
    {
        $id = (int) $this->params()->fromRoute('id', 0);

        $ac = $this->entityManager->find(Ac::class, $id);

        if ($ac === null) {
            $this->getResponse()->setStatusCode(404);

            return new ViewModel([
                'ac' => null,
            ]);
        }

        if ($this->getRequest()->isPost()) {
            $nome = trim(
                (string) $this->params()->fromPost('nome', '')
            );

            if ($nome !== '') {
                $ac->setNome($nome);

                $this->entityManager->flush();

                return $this->redirect()->toRoute('ac');
            }
        }

        return new ViewModel([
            'ac' => $ac,
        ]);
    }
}
