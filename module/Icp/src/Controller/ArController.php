<?php

declare(strict_types=1);

namespace Icp\Controller;

use Application\Form\CsrfForm;
use Icp\Entity\AcN2;
use Icp\Entity\Ar;
use Icp\Enum\Situacao;
use Icp\Form\ArForm;
use Icp\Repository\AcN2Repository;
use Icp\Repository\ArRepository;
use Laminas\Http\Response;
use Laminas\View\Model\ViewModel;

use function array_map;
use function sprintf;

final class ArController extends AbstractIcpController
{
    public function indexAction(): ViewModel
    {
        $busca    = $this->buscaInformada();
        $acN2Id   = $this->inteiroDaQuery('acN2');
        $situacao = $this->situacaoDaQuery();

        /** @var ArRepository $repositorio */
        $repositorio = $this->entityManager->getRepository(Ar::class);

        return new ViewModel([
            'ars'          => $this->paginar($repositorio->createListagemQuery($busca, $acN2Id, $situacao), true),
            'busca'        => $busca,
            'acN2Id'       => $acN2Id,
            'situacao'     => $situacao,
            'acN2s'        => $this->repositorioAcN2()->findAllComAc(),
            'formExclusao' => $this->formularioExclusao(),
        ]);
    }

    public function viewAction(): ViewModel
    {
        $ar = $this->entityManager->find(Ar::class, $this->idDaRota());
        if (! $ar instanceof Ar) {
            return $this->naoEncontrado('A Autoridade de Registro solicitada não existe.');
        }

        return new ViewModel(['ar' => $ar, 'formExclusao' => $this->formularioExclusao()]);
    }

    public function createAction(): ViewModel|Response
    {
        $acN2s = $this->repositorioAcN2()->findAllComAc();
        if ($acN2s === []) {
            $this->flashMessenger()->addWarningMessage('Cadastre uma AC N2 antes de cadastrar uma AR.');

            return $this->redirect()->toRoute('ac-n2/create');
        }

        $form       = new ArForm($acN2s);
        $acN2Inicial = $this->inteiroDaQuery('acN2');
        $form->setData([
            'acN2s'    => $acN2Inicial !== null ? [$acN2Inicial] : [],
            'situacao' => Situacao::Credenciado->value,
        ]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->dadosDoPost());

            if ($form->isValid()) {
                /** @var array{nome: string, acN2s: list<int>, situacao: int} $dados */
                $dados = $form->getData();
                $ar    = new Ar($dados['nome'], Situacao::from($dados['situacao']));
                $ar->definirAcN2s($this->repositorioAcN2()->findByIds($dados['acN2s']));

                $this->entityManager->persist($ar);
                $this->entityManager->flush();

                $this->flashMessenger()->addSuccessMessage(sprintf('AR "%s" cadastrada.', $ar->getNome()));

                return $this->redirect()->toRoute('ar/view', ['id' => $ar->getId()]);
            }
        }

        return $this->formulario($form, 'Cadastrar Autoridade de Registro', null);
    }

    public function editAction(): ViewModel|Response
    {
        $ar = $this->entityManager->find(Ar::class, $this->idDaRota());
        if (! $ar instanceof Ar) {
            return $this->naoEncontrado('A Autoridade de Registro solicitada não existe.');
        }

        $form = new ArForm($this->repositorioAcN2()->findAllComAc());
        $form->setData([
            'nome'     => $ar->getNome(),
            'acN2s'    => array_map(static fn (AcN2 $n): int => (int) $n->getId(), $ar->getAcN2s()->toArray()),
            'situacao' => $ar->getSituacao()->value,
        ]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->dadosDoPost());

            if ($form->isValid()) {
                /** @var array{nome: string, acN2s: list<int>, situacao: int} $dados */
                $dados = $form->getData();
                $ar->setNome($dados['nome']);
                $ar->setSituacao(Situacao::from($dados['situacao']));
                $ar->definirAcN2s($this->repositorioAcN2()->findByIds($dados['acN2s']));

                $this->entityManager->flush();

                $this->flashMessenger()->addSuccessMessage(sprintf('AR "%s" atualizada.', $ar->getNome()));

                return $this->redirect()->toRoute('ar/view', ['id' => $ar->getId()]);
            }
        }

        return $this->formulario($form, 'Editar Autoridade de Registro', $ar);
    }

    public function deleteAction(): Response
    {
        if (! $this->getRequest()->isPost()) {
            return $this->metodoNaoPermitido();
        }

        $ar = $this->entityManager->find(Ar::class, $this->idDaRota());

        if (! $this->csrfValido()) {
            $this->flashMessenger()->addErrorMessage(CsrfForm::MENSAGEM_TOKEN_INVALIDO);
        } elseif (! $ar instanceof Ar) {
            $this->flashMessenger()->addErrorMessage('A Autoridade de Registro solicitada não existe.');
        } else {
            // Os vínculos em ar_ac_n2 são removidos junto (ON DELETE CASCADE).
            $nome = $ar->getNome();
            $this->entityManager->remove($ar);
            $this->entityManager->flush();
            $this->flashMessenger()->addSuccessMessage(sprintf('AR "%s" excluída.', $nome));
        }

        return $this->redirect()->toRoute('ar');
    }

    /**
     * Um <select multiple> sem nada marcado não envia o campo; garante a chave
     * para que a validação mostre a mensagem correta.
     *
     * @return array<string, mixed>
     */
    private function dadosDoPost(): array
    {
        return $this->params()->fromPost() + ['acN2s' => []];
    }

    private function repositorioAcN2(): AcN2Repository
    {
        /** @var AcN2Repository $repositorio */
        $repositorio = $this->entityManager->getRepository(AcN2::class);

        return $repositorio;
    }

    private function formulario(ArForm $form, string $titulo, ?Ar $ar): ViewModel
    {
        $view = new ViewModel(['form' => $form, 'titulo' => $titulo, 'ar' => $ar]);
        $view->setTemplate('icp/ar/form');

        return $view;
    }
}
