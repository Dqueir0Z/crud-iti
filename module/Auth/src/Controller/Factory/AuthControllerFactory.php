<?php

declare(strict_types=1);

namespace Auth\Controller\Factory;

use Auth\Controller\AuthController;
use Auth\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Session\SessionManager;
use Psr\Container\ContainerInterface;

final class AuthControllerFactory
{
    public function __invoke(ContainerInterface $container): AuthController
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.entitymanager.orm_default');

        return new AuthController(
            $container->get(AuthenticationService::class),
            $entityManager->getRepository(Usuario::class),
            $container->get(SessionManager::class)
        );
    }
}
