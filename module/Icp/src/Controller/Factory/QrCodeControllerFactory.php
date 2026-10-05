<?php

declare(strict_types=1);

namespace Icp\Controller\Factory;

use Icp\Controller\QrCodeController;
use Icp\Service\GeradorQrCode;
use Icp\Service\LinkQrCode;
use Psr\Container\ContainerInterface;

final class QrCodeControllerFactory
{
    public function __invoke(ContainerInterface $container): QrCodeController
    {
        return new QrCodeController(
            $container->get('doctrine.entitymanager.orm_default'),
            $container->get(GeradorQrCode::class),
            $container->get(LinkQrCode::class)
        );
    }
}
