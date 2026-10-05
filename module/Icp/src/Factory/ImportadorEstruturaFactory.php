<?php

declare(strict_types=1);

namespace Icp\Factory;

use Icp\Service\ImportadorEstrutura;
use Icp\Service\LeitorEstrutura;
use Psr\Container\ContainerInterface;

final class ImportadorEstruturaFactory
{
    public function __invoke(ContainerInterface $container): ImportadorEstrutura
    {
        return new ImportadorEstrutura(
            $container->get('doctrine.entitymanager.orm_default'),
            new LeitorEstrutura()
        );
    }
}
