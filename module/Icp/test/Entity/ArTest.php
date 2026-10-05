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
    private Ac $ac;

    protected function setUp(): void
    {
        $this->ac = new Ac('AC');
    }

    public function testVincularNaoDuplica(): void
    {
        $acN2 = new AcN2('AC N2', $this->ac);
        $ar   = new Ar('AR');

        self::assertTrue($ar->vincularAcN2($acN2));
        self::assertFalse($ar->vincularAcN2($acN2));
        self::assertCount(1, $ar->getAcN2s());
    }

    public function testDefinirAcN2sSubstituiOsVinculosEmOrdemAlfabetica(): void
    {
        $a = new AcN2('A', $this->ac);
        $b = new AcN2('B', $this->ac);
        $c = new AcN2('C', $this->ac);

        $ar = new Ar('AR', Situacao::EmCredenciamento);
        $ar->definirAcN2s([$b, $a]);
        $ar->definirAcN2s([$c, $b]);

        self::assertSame([$b, $c], $ar->getAcN2s());
        self::assertSame(Situacao::EmCredenciamento, $ar->getSituacao());
    }

    /** Achado B1 (revisão final do Codex): a edição não pode uniformizar as situações. */
    public function testDefinirAcN2sPreservaASituacaoDosVinculosQueContinuam(): void
    {
        $a = new AcN2('A', $this->ac);
        $b = new AcN2('B', $this->ac);
        $c = new AcN2('C', $this->ac);

        $ar = new Ar('AR', Situacao::Credenciado);
        $ar->vincularAcN2($a, Situacao::EmCredenciamento);
        $ar->vincularAcN2($b, Situacao::Credenciado);

        $ar->definirAcN2s([$a, $c]);

        $situacoes = [];
        foreach ($ar->getVinculos() as $vinculo) {
            $situacoes[$vinculo->getAcN2()->getNome()] = $vinculo->getSituacao();
        }
        self::assertSame(['A' => Situacao::EmCredenciamento, 'C' => Situacao::Credenciado], $situacoes);
        self::assertTrue($ar->temSituacoesDiferentes());
    }

    /**
     * Retirar e devolver a mesma AC N2 antes do flush reaproveita o vínculo:
     * outra instância com a mesma chave causaria INSERT antes do DELETE.
     */
    public function testRetirarEDevolverAMesmaAcN2ReaproveitaOVinculo(): void
    {
        $a  = new AcN2('A', $this->ac);
        $ar = new Ar('AR');
        $ar->vincularAcN2($a, Situacao::EmCredenciamento);
        $original = $ar->getVinculos()[0];

        $ar->definirAcN2s([]);
        $ar->definirAcN2s([$a]);

        self::assertSame($original, $ar->getVinculos()[0]);
        self::assertSame(Situacao::EmCredenciamento, $original->getSituacao());
    }

    public function testDefinirSituacaoDoVinculo(): void
    {
        $a  = new AcN2('A', $this->ac);
        $b  = new AcN2('B', $this->ac);
        $ar = new Ar('AR');
        $ar->vincularAcN2($a);

        self::assertTrue($ar->definirSituacaoDoVinculo($a, Situacao::EmCredenciamento));
        self::assertFalse($ar->definirSituacaoDoVinculo($a, Situacao::EmCredenciamento), 'sem mudança');
        self::assertFalse($ar->definirSituacaoDoVinculo($b, Situacao::EmCredenciamento), 'vínculo inexistente');
    }

    public function testSituacaoGeralEhCredenciadoSeAlgumVinculoForCredenciado(): void
    {
        $a  = new AcN2('A', $this->ac);
        $b  = new AcN2('B', $this->ac);
        $ar = new Ar('AR', Situacao::Credenciado);

        self::assertFalse($ar->recalcularSituacao(), 'sem vínculos mantém a atual');

        $ar->vincularAcN2($a, Situacao::EmCredenciamento);
        self::assertTrue($ar->recalcularSituacao());
        self::assertSame(Situacao::EmCredenciamento, $ar->getSituacao());

        $ar->vincularAcN2($b, Situacao::Credenciado);
        self::assertTrue($ar->recalcularSituacao());
        self::assertSame(Situacao::Credenciado, $ar->getSituacao());
    }

    public function testAplicarSituacaoATodosAlteraAArEOsVinculos(): void
    {
        $a  = new AcN2('A', $this->ac);
        $b  = new AcN2('B', $this->ac);
        $ar = new Ar('AR', Situacao::Credenciado);
        $ar->vincularAcN2($a, Situacao::EmCredenciamento);
        $ar->vincularAcN2($b, Situacao::Credenciado);

        $ar->aplicarSituacaoATodos(Situacao::EmCredenciamento);

        self::assertSame(Situacao::EmCredenciamento, $ar->getSituacao());
        self::assertFalse($ar->temSituacoesDiferentes());
        foreach ($ar->getVinculos() as $vinculo) {
            self::assertSame(Situacao::EmCredenciamento, $vinculo->getSituacao());
        }
    }
}
