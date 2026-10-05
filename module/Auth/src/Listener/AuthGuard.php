<?php

declare(strict_types=1);

namespace Auth\Listener;

use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\MvcEvent;

use function in_array;

/**
 * Exige usuário autenticado em todas as rotas, exceto as públicas.
 * Roda após o roteamento; requisições sem identidade são enviadas ao login.
 */
final class AuthGuard
{
    /** @var list<string> */
    public const ROTAS_PUBLICAS = ['login'];

    public function __construct(private AuthenticationServiceInterface $autenticacao)
    {
    }

    public function __invoke(MvcEvent $evento): ?Response
    {
        $rota = $evento->getRouteMatch();

        // Sem rota correspondente: deixa o fluxo normal gerar o 404.
        if ($rota === null || in_array($rota->getMatchedRouteName(), self::ROTAS_PUBLICAS, true)) {
            return null;
        }

        if ($this->autenticacao->hasIdentity()) {
            return null;
        }

        $request  = $evento->getRequest();
        $opcoes   = ['name' => 'login'];
        if ($request instanceof Request && $request->isGet()) {
            $opcoes['query'] = ['redirect' => $request->getUri()->getPath()
                . ($request->getUri()->getQuery() ? '?' . $request->getUri()->getQuery() : '')];
        }

        $response = $evento->getResponse();
        if (! $response instanceof Response) {
            $response = new Response();
        }

        $response->getHeaders()->addHeaderLine('Location', $evento->getRouter()->assemble([], $opcoes));
        $response->setStatusCode(Response::STATUS_CODE_302);

        return $response;
    }
}
