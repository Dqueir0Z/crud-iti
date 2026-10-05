<?php

declare(strict_types=1);

namespace Icp\Factory;

use Icp\Service\LinkQrCode;
use Psr\Container\ContainerInterface;

final class LinkQrCodeFactory
{
    public function __invoke(ContainerInterface $container): LinkQrCode
    {
        /** @var array{icp?: array{qrcode?: array{base_url?: string|null}}} $config */
        $config = $container->get('config');

        return new LinkQrCode(
            $container->get('HttpRouter'),
            $config['icp']['qrcode']['base_url'] ?? null
        );
    }
}
