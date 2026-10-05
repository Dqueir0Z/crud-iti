<?php

declare(strict_types=1);

namespace Icp\Controller;

use Application\Form\CsrfForm;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Icp\Entity\Ac;
use Icp\Entity\AcN2;
use Icp\Entity\Ar;
use Icp\Enum\Situacao;
use Icp\Form\AcN2Form;
use Icp\Repository\AcN2Repository;
use Icp\Repository\AcRepository;
use Icp\Repository\ArRepository;
use Laminas\Http\Response;
use Laminas\View\Model\ViewModel;

use function sprintf;

final class AcN2Controller extends AbstractIcpController
{
    private const LIMITE_AR_NO_DETALHE = 50;

    public function indexAction(): ViewModel
    {
        $busca    = $this->buscaInformada();
        $acId     = $this->inteiroDaQuery('ac');
        $situacao = $this->situacaoDaQuery();

        /** @var AcN2Repository $repositorio */
        $repositorio = $this->entityManager->getRepository(AcN2::class);

        return new ViewModel([
            'acN2s'        => $this->paginar($repositorio->createListagemQuery($busca, $acId, $situacao)),
            'busca'        => $busca,
            'acId'         => $acId,
            'situacao'     => $situacao,
            'acs'          => $this->acsDisponiveis(),
            'formExclusao' => $this->formularioExclusao(),
        ]);
    }

    public function viewAction(): ViewModel
    {
        $acN2 = $this->entityManager->find(AcN2::class, $this->idDaRota());
        if (! $acN2 instanceof AcN2) {
            return $this->naoEncontrado('A AC N2 solicitada não existe.');
        }

        /** @var ArRepository $ars */
        $ars = $this->entityManager->getRepository(Ar::class);

        return new ViewModel([
            'acN2'         => $acN2,
            'totalArs'     => $acN2->contarArs(),
            'vinculos'     => $ars->findVinculosDaAcN2($acN2, self::LIMITE_AR_NO_DETALHE),
            'limite'       => self::LIMITE_AR_NO_DETALHE,
            'formExclusao' => $this->formularioExclusao(),
        ]);
    }

    public function createAction(): ViewModel|Response
    {
        $acs = $this->acsDisponiveis();
        if ($acs === []) {
            $this->flashMessenger()->addWarningMessage('Cadastre uma AC antes de cadastrar uma AC N2.');

            return $this->redirect()->toRoute('ac/create');
        }

        $form = new AcN2Form($acs);
        $form->setData([
            'ac'       => $this->inteiroDaQuery('ac'),
            'situacao' => Situacao::Credenciado->value,
        ]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());

            if ($form->isValid()) {
                /** @var array{nome: string, ac: int, situacao: int} $dados */
                $dados = $form->getData();
                $acN2  = new AcN2(
                    $dados['nome'],
                    $this->entityManager->getReference(Ac::class, $dados['ac']),
                    Situacao::from($dados['situacao'])
                );

                $this->entityManager->persist($acN2);
                $this->entityManager->flush();

                $this->flashMessenger()->addSuccessMessage(sprintf('AC N2 "%s" cadastrada.', $acN2->getNome()));

                return $this->redirect()->toRoute('ac-n2/view', ['id' => $acN2->getId()]);
            }
        }

        return $this->formulario($form, 'Cadastrar AC de 2º nível', null);
    }

    public function editAction(): ViewModel|Response
    {
        $acN2 = $this->entityManager->find(AcN2::class, $this->idDaRota());
        if (! $acN2 instanceof AcN2) {
            return $this->naoEncontrado('A AC N2 solicitada não existe.');
        }

        $form = new AcN2Form($this->acsDisponiveis());
        $form->setData([
            'nome'     => $acN2->getNome(),
            'ac'       => $acN2->getAc()->getId(),
            'situacao' => $acN2->getSituacao()->value,
        ]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());

            if ($form->isValid()) {
                /** @var array{nome: string, ac: int, situacao: int} $dados */
                $dados = $form->getData();
                $acN2->setNome($dados['nome']);
                $acN2->setAc($this->entityManager->getReference(Ac::class, $dados['ac']));
                $acN2->setSituacao(Situacao::from($dados['situacao']));

                $this->entityManager->flush();

                $this->flashMessenger()->addSuccessMessage(sprintf('AC N2 "%s" atualizada.', $acN2->getNome()));

                return $this->redirect()->toRoute('ac-n2/view', ['id' => $acN2->getId()]);
            }
        }

        return $this->formulario($form, 'Editar AC de 2º nível', $acN2);
    }

    public function deleteAction(): Response
    {
        if (! $this->getRequest()->isPost()) {
            return $this->metodoNaoPermitido();
        }

        $acN2 = $this->entityManager->find(AcN2::class, $this->idDaRota());

        if (! $this->csrfValido()) {
            $this->flashMessenger()->addErrorMessage(CsrfForm::MENSAGEM_TOKEN_INVALIDO);
        } elseif (! $acN2 instanceof AcN2) {
            $this->flashMessenger()->addErrorMessage('A AC N2 solicitada não existe.');
        } elseif (($total = $acN2->contarArs()) > 0) {
            $this->flashMessenger()->addErrorMessage(sprintf(
                'Não é possível excluir a AC N2 "%s": ela possui %d AR vinculada(s). Remova esses vínculos antes.',
                $acN2->getNome(),
                $total
            ));

            return $this->redirect()->toRoute('ac-n2/view', ['id' => $acN2->getId()]);
        } else {
            // A checagem acima só serve para a mensagem; quem garante a regra é o
            // FK RESTRICT de ar_ac_n2 (uma AR vinculada entre a checagem e o
            // DELETE faz o banco recusar a exclusão).
            try {
                $nome = $acN2->getNome();
                $this->entityManager->remove($acN2);
                $this->entityManager->flush();
                $this->flashMessenger()->addSuccessMessage(sprintf('AC N2 "%s" excluída.', $nome));
            } catch (ForeignKeyConstraintViolationException) {
                $this->flashMessenger()->addErrorMessage('Não é possível excluir: existem AR vinculadas a esta AC N2.');
            }
        }

        return $this->redirect()->toRoute('ac-n2');
    }

    /** @return list<Ac> */
    private function acsDisponiveis(): array
    {
        /** @var AcRepository $repositorio */
        $repositorio = $this->entityManager->getRepository(Ac::class);

        return $repositorio->findAllOrdenadas();
    }

    private function formulario(AcN2Form $form, string $titulo, ?AcN2 $acN2): ViewModel
    {
        $view = new ViewModel(['form' => $form, 'titulo' => $titulo, 'acN2' => $acN2]);
        $view->setTemplate('icp/ac-n2/form');

        return $view;
    }
}
