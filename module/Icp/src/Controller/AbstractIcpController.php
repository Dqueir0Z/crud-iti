<?php

declare(strict_types=1);

namespace Icp\Controller;

use Application\Form\CsrfForm;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator as OrmPaginator;
use DoctrineORMModule\Paginator\Adapter\DoctrinePaginator;
use Icp\Enum\Situacao;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator\Paginator;
use Laminas\View\Model\ViewModel;

use function is_numeric;
use function is_string;
use function max;
use function trim;

/**
 * Comportamento comum aos CRUDs: paginação, filtros da listagem,
 * resposta 404 e verificação do token CSRF em ações sem formulário.
 */
abstract class AbstractIcpController extends AbstractActionController
{
    protected const ITENS_POR_PAGINA = 25;

    public function __construct(protected EntityManagerInterface $entityManager)
    {
    }

    protected function paginar(Query $query, bool $comJoinDeColecao = false): Paginator
    {
        $paginator = new Paginator(new DoctrinePaginator(new OrmPaginator($query, $comJoinDeColecao)));
        $paginator->setItemCountPerPage(static::ITENS_POR_PAGINA);
        $paginator->setCurrentPageNumber(max(1, (int) $this->params()->fromQuery('pagina', 1)));

        return $paginator;
    }

    protected function idDaRota(): int
    {
        return (int) $this->params()->fromRoute('id', 0);
    }

    protected function buscaInformada(): string
    {
        $busca = $this->params()->fromQuery('busca', '');

        return is_string($busca) ? trim($busca) : '';
    }

    protected function inteiroDaQuery(string $nome): ?int
    {
        $valor = $this->params()->fromQuery($nome);

        return is_numeric($valor) && (int) $valor > 0 ? (int) $valor : null;
    }

    protected function situacaoDaQuery(): ?Situacao
    {
        $valor = $this->params()->fromQuery('situacao');

        return is_numeric($valor) ? Situacao::tryFrom((int) $valor) : null;
    }

    protected function naoEncontrado(string $mensagem): ViewModel
    {
        $this->getResponse()->setStatusCode(Response::STATUS_CODE_404);

        $view = new ViewModel(['mensagem' => $mensagem]);
        $view->setTemplate('icp/partial/nao-encontrado');

        return $view;
    }

    /** Valida o token CSRF de um POST sem outros campos (ex.: exclusão). */
    protected function csrfValido(): bool
    {
        return $this->getRequest()->isPost()
            && (new CsrfForm())->setData($this->params()->fromPost())->isValid();
    }

    /** Resposta 405 para ações que só aceitam POST (ex.: exclusão acessada por link). */
    protected function metodoNaoPermitido(): Response
    {
        /** @var Response $response */
        $response = $this->getResponse();
        $response->setStatusCode(Response::STATUS_CODE_405);
        $response->getHeaders()->addHeaderLine('Allow', 'POST');
        $response->setContent('Método não permitido. A exclusão deve ser feita pelo botão Excluir.');

        return $response;
    }

    /** Formulário apenas com o token, usado pelos botões de exclusão. */
    protected function formularioExclusao(): CsrfForm
    {
        return new CsrfForm();
    }
}
