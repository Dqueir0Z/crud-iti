<?php

declare(strict_types=1);

namespace Icp\Factory;

use Icp\Service\LinkQrCode;
use Icp\View\Helper\BotaoQrCode;
use Psr\Container\ContainerInterface;

final class BotaoQrCodeFactory
{
    public function __invoke(ContainerInterface $container): BotaoQrCode
    {
        return new BotaoQrCode($container->get(LinkQrCode::class));
    }
}
