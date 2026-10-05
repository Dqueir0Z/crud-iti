<?php

declare(strict_types=1);

namespace Icp\Controller\Factory;

use Icp\Controller\AbstractIcpController;
use Psr\Container\ContainerInterface;

/**
 * Cria controllers que dependem apenas do EntityManager (AC, AC N2, AR, Estrutura).
 */
final class EntityManagerControllerFactory
{
    /** @param class-string<AbstractIcpController> $requestedName */
    public function __invoke(ContainerInterface $container, string $requestedName): AbstractIcpController
    {
        return new $requestedName($container->get('doctrine.entitymanager.orm_default'));
    }
}
