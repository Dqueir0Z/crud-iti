<?php

declare(strict_types=1);

namespace Icp\Service;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Gera QR Codes em SVG com chillerlan/php-qrcode.
 * SVG não depende da extensão GD e escala sem perda no modal.
 */
final class GeradorQrCode
{
    public function svg(string $conteudo): string
    {
        $opcoes = new QROptions([
            'outputType'      => QROutputInterface::MARKUP_SVG,
            'outputBase64'    => false,
            'eccLevel'        => EccLevel::M,
            'addQuietzone'    => true,
            'quietzoneSize'   => 2,
            'svgAddXmlHeader' => true,
            'connectPaths'    => true,
            'drawLightModules' => false,
        ]);

        return (new QRCode($opcoes))->render($conteudo);
    }
}
