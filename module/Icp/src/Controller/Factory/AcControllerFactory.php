<?php

declare(strict_types=1);

namespace Icp\Controller\Factory;

use Doctrine\ORM\EntityManagerInterface;
use Icp\Controller\AcController;
use Psr\Container\ContainerInterface;

class AcControllerFactory
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null
    ): AcController {
        $entityManager = $container->get(
            'doctrine.entitymanager.orm_default'
        );

        return new AcController($entityManager);
    }
}
