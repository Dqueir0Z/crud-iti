<?php

declare(strict_types=1);

namespace Auth\Controller;

use Application\Form\CsrfForm;
use Auth\Entity\Usuario;
use Auth\Form\LoginForm;
use Auth\Service\CredenciaisAdapter;
use Doctrine\Persistence\ObjectRepository;
use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\SessionManager;
use Laminas\View\Model\ViewModel;

use function is_string;
use function str_starts_with;

final class AuthController extends AbstractActionController
{
    /** @param ObjectRepository<Usuario> $usuarios */
    public function __construct(
        private AuthenticationServiceInterface $autenticacao,
        private ObjectRepository $usuarios,
        private SessionManager $sessao
    ) {
    }

    public function loginAction(): ViewModel|Response
    {
        $destino = $this->destinoSeguro($this->params()->fromQuery('redirect'));

        if ($this->autenticacao->hasIdentity()) {
            return $this->redirect()->toUrl($destino);
        }

        $form = new LoginForm();
        $erro = null;

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());

            if ($form->isValid()) {
                /** @var array{email: string, senha: string} $dados */
                $dados     = $form->getData();
                $resultado = $this->autenticacao->authenticate(
                    new CredenciaisAdapter($this->usuarios, $dados['email'], $dados['senha'])
                );

                if ($resultado->isValid()) {
                    // Novo id de sessão após o login evita fixação de sessão.
                    $this->sessao->regenerateId(true);
                    $this->autenticacao->getStorage()->write($resultado->getIdentity());

                    return $this->redirect()->toUrl($destino);
                }

                $erro = CredenciaisAdapter::MENSAGEM_FALHA;
                $form->get('senha')->setValue('');
            }
        }

        $view = new ViewModel(['form' => $form, 'erro' => $erro, 'destino' => $destino]);
        $view->setTemplate('auth/auth/login');

        return $view;
    }

    public function logoutAction(): Response
    {
        $form = new CsrfForm('logout');

        if ($this->getRequest()->isPost() && $form->setData($this->params()->fromPost())->isValid()) {
            $this->autenticacao->clearIdentity();
            $this->sessao->regenerateId(true);
            $this->flashMessenger()->addInfoMessage('Sessão encerrada.');
        }

        return $this->redirect()->toRoute('login');
    }

    /** Aceita apenas caminhos locais para evitar redirecionamento aberto. */
    private function destinoSeguro(mixed $destino): string
    {
        if (
            is_string($destino) && str_starts_with($destino, '/') && ! str_starts_with($destino, '//')
            && ! str_starts_with($destino, '/\\')
        ) {
            return $destino;
        }

        return $this->url()->fromRoute('home');
    }
}
