<?php

declare(strict_types=1);

namespace Icp\Controller\Factory;

use Icp\Controller\ImportacaoController;
use Icp\Service\ImportadorEstrutura;
use Psr\Container\ContainerInterface;

final class ImportacaoControllerFactory
{
    public function __invoke(ContainerInterface $container): ImportacaoController
    {
        return new ImportacaoController($container->get(ImportadorEstrutura::class));
    }
}
