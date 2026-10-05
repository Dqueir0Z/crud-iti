<?php

declare(strict_types=1);

namespace IcpTest\Entity;

use Icp\Entity\Ac;
use Icp\Entity\AcN2;
use Icp\Entity\Ar;
use Icp\Enum\Situacao;
use PHPUnit\Framework\TestCase;

final class ArTest extends TestCase
{
    public function testVincularNaoDuplica(): void
    {
        $acN2 = new AcN2('AC N2', new Ac('AC'));
        $ar   = new Ar('AR');

        self::assertTrue($ar->vincularAcN2($acN2));
        self::assertFalse($ar->vincularAcN2($acN2));
        self::assertCount(1, $ar->getAcN2s());
    }

    public function testDefinirAcN2sSubstituiOsVinculos(): void
    {
        $ac = new Ac('AC');
        $a  = new AcN2('A', $ac);
        $b  = new AcN2('B', $ac);
        $c  = new AcN2('C', $ac);

        $ar = new Ar('AR', Situacao::EmCredenciamento);
        $ar->definirAcN2s([$a, $b]);
        $ar->definirAcN2s([$b, $c]);

        self::assertSame([$b, $c], array_values($ar->getAcN2s()->toArray()));
        self::assertSame(Situacao::EmCredenciamento, $ar->getSituacao());
    }
}
