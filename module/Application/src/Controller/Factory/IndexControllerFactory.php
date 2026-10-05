<?php

declare(strict_types=1);

namespace Application\Controller\Factory;

use Application\Controller\IndexController;
use Psr\Container\ContainerInterface;

final class IndexControllerFactory
{
    public function __invoke(ContainerInterface $container): IndexController
    {
        return new IndexController($container->get('doctrine.entitymanager.orm_default'));
    }
}
