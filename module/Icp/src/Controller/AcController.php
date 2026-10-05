<?php

declare(strict_types=1);

namespace Icp\Controller;

use Application\Form\CsrfForm;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Icp\Entity\Ac;
use Icp\Enum\Situacao;
use Icp\Form\AcForm;
use Icp\Repository\AcRepository;
use Laminas\Http\Response;
use Laminas\View\Model\ViewModel;

use function sprintf;

final class AcController extends AbstractIcpController
{
    public function indexAction(): ViewModel
    {
        $busca    = $this->buscaInformada();
        $situacao = $this->situacaoDaQuery();

        /** @var AcRepository $repositorio */
        $repositorio = $this->entityManager->getRepository(Ac::class);

        return new ViewModel([
            'acs'           => $this->paginar($repositorio->createListagemQuery($busca, $situacao)),
            'busca'         => $busca,
            'situacao'      => $situacao,
            'formExclusao'  => $this->formularioExclusao(),
        ]);
    }

    public function viewAction(): ViewModel
    {
        $ac = $this->entityManager->find(Ac::class, $this->idDaRota());
        if (! $ac instanceof Ac) {
            return $this->naoEncontrado('A Autoridade Certificadora solicitada não existe.');
        }

        return new ViewModel(['ac' => $ac, 'formExclusao' => $this->formularioExclusao()]);
    }

    public function createAction(): ViewModel|Response
    {
        $form = new AcForm();
        $form->get('situacao')->setValue(Situacao::Credenciado->value);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());

            if ($form->isValid()) {
                /** @var array{nome: string, situacao: int} $dados */
                $dados = $form->getData();
                $ac    = new Ac($dados['nome'], Situacao::from($dados['situacao']));

                $this->entityManager->persist($ac);
                $this->entityManager->flush();

                $this->flashMessenger()->addSuccessMessage(sprintf('AC "%s" cadastrada.', $ac->getNome()));

                return $this->redirect()->toRoute('ac/view', ['id' => $ac->getId()]);
            }
        }

        return $this->formulario($form, 'Cadastrar Autoridade Certificadora', null);
    }

    public function editAction(): ViewModel|Response
    {
        $ac = $this->entityManager->find(Ac::class, $this->idDaRota());
        if (! $ac instanceof Ac) {
            return $this->naoEncontrado('A Autoridade Certificadora solicitada não existe.');
        }

        $form = new AcForm();
        $form->setData(['nome' => $ac->getNome(), 'situacao' => $ac->getSituacao()->value]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());

            if ($form->isValid()) {
                /** @var array{nome: string, situacao: int} $dados */
                $dados = $form->getData();
                $ac->setNome($dados['nome']);
                $ac->setSituacao(Situacao::from($dados['situacao']));

                $this->entityManager->flush();

                $this->flashMessenger()->addSuccessMessage(sprintf('AC "%s" atualizada.', $ac->getNome()));

                return $this->redirect()->toRoute('ac/view', ['id' => $ac->getId()]);
            }
        }

        return $this->formulario($form, 'Editar Autoridade Certificadora', $ac);
    }

    public function deleteAction(): Response
    {
        if (! $this->getRequest()->isPost()) {
            return $this->metodoNaoPermitido();
        }

        $ac = $this->entityManager->find(Ac::class, $this->idDaRota());

        if (! $this->csrfValido()) {
            $this->flashMessenger()->addErrorMessage(CsrfForm::MENSAGEM_TOKEN_INVALIDO);
        } elseif (! $ac instanceof Ac) {
            $this->flashMessenger()->addErrorMessage('A Autoridade Certificadora solicitada não existe.');
        } elseif (($total = $ac->getAcN2s()->count()) > 0) {
            $this->flashMessenger()->addErrorMessage(sprintf(
                'Não é possível excluir a AC "%s": ela possui %d AC N2 vinculada(s). Exclua ou transfira essas AC N2 antes.',
                $ac->getNome(),
                $total
            ));

            return $this->redirect()->toRoute('ac/view', ['id' => $ac->getId()]);
        } else {
            try {
                $nome = $ac->getNome();
                $this->entityManager->remove($ac);
                $this->entityManager->flush();
                $this->flashMessenger()->addSuccessMessage(sprintf('AC "%s" excluída.', $nome));
            } catch (ForeignKeyConstraintViolationException) {
                // Segurança extra caso um vínculo seja criado entre a verificação e o DELETE.
                $this->flashMessenger()->addErrorMessage('Não é possível excluir: existem registros vinculados a esta AC.');
            }
        }

        return $this->redirect()->toRoute('ac');
    }

    private function formulario(AcForm $form, string $titulo, ?Ac $ac): ViewModel
    {
        $view = new ViewModel(['form' => $form, 'titulo' => $titulo, 'ac' => $ac]);
        $view->setTemplate('icp/ac/form');

        return $view;
    }
}
