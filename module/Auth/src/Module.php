<?php

declare(strict_types=1);

namespace Auth;

use Auth\Listener\AuthGuard;
use Laminas\Authentication\AuthenticationService;
use Laminas\Mvc\MvcEvent;
use Laminas\Session\SessionManager;

class Module
{
    public function getConfig(): array
    {
        /** @var array $config */
        $config = include __DIR__ . '/../config/module.config.php';
        return $config;
    }

    public function onBootstrap(MvcEvent $evento): void
    {
        $container = $evento->getApplication()->getServiceManager();

        // Instanciar o SessionManager o registra como padrão dos containers
        // de sessão (usados pelo token CSRF e pelo FlashMessenger).
        $container->get(SessionManager::class);

        // Prioridade negativa: roda depois do roteamento (RouteListener).
        $evento->getApplication()->getEventManager()->attach(
            MvcEvent::EVENT_ROUTE,
            static fn (MvcEvent $e) => (new AuthGuard($container->get(AuthenticationService::class)))($e),
            -100
        );
    }
}
