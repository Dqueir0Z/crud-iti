<?php

declare(strict_types=1);

namespace Auth\Factory;

use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\Session as SessionStorage;
use Laminas\Session\SessionManager;
use Psr\Container\ContainerInterface;

final class AuthenticationServiceFactory
{
    public function __invoke(ContainerInterface $container): AuthenticationService
    {
        return new AuthenticationService(
            new SessionStorage('crud_iti_auth', 'usuario', $container->get(SessionManager::class))
        );
    }
}
