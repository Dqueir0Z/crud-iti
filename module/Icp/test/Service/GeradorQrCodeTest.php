<?php

declare(strict_types=1);

namespace IcpTest\Service;

use Icp\Service\GeradorQrCode;
use PHPUnit\Framework\TestCase;

final class GeradorQrCodeTest extends TestCase
{
    public function testGeraSvgPuro(): void
    {
        $svg = (new GeradorQrCode())->svg('http://localhost:8080/ac/view/42');

        self::assertStringStartsWith('<?xml', $svg);
        self::assertStringContainsString('<svg', $svg);
        self::assertStringNotContainsString('data:image', $svg, 'Deve devolver o SVG puro, não base64.');
    }

    public function testConteudoDiferenteGeraQrCodeDiferente(): void
    {
        $gerador = new GeradorQrCode();

        self::assertSame($gerador->svg('http://x/ac/view/1'), $gerador->svg('http://x/ac/view/1'));
        self::assertNotSame($gerador->svg('http://x/ac/view/1'), $gerador->svg('http://x/ac/view/2'));
    }
}
