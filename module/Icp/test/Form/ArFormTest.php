<?php

declare(strict_types=1);

namespace IcpTest\Form;

use Icp\Entity\Ac;
use Icp\Entity\AcN2;
use Icp\Form\ArForm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Regressão: a seleção múltipla de AC N2 era rejeitada mesmo com ids válidos,
 * porque os valores chegavam como string e não eram convertidos.
 */
final class ArFormTest extends TestCase
{
    private function formulario(): ArForm
    {
        $ac = $this->comId(new Ac('AC'), 1);
        $form = new ArForm([
            $this->comId(new AcN2('N5', $ac), 5),
            $this->comId(new AcN2('N7', $ac), 7),
        ]);
        $form->remove('csrf');

        return $form;
    }

    /**
     * @template T of object
     * @param T $entidade
     * @return T
     */
    private function comId(object $entidade, int $id): object
    {
        (new ReflectionProperty($entidade, 'id'))->setValue($entidade, $id);

        return $entidade;
    }

    public function testIdsValidosVindosDoPostSaoAceitosEConvertidos(): void
    {
        $form = $this->formulario();
        $form->setData(['nome' => 'AR X', 'acN2s' => ['5', '7', '5'], 'situacao' => '4002']);

        self::assertTrue($form->isValid(), json_encode($form->getMessages()) ?: '');
        self::assertSame([5, 7], $form->getData()['acN2s']);
        self::assertSame(4002, $form->getData()['situacao']);
    }

    /** @return iterable<string, array{mixed}> */
    public static function selecoesInvalidas(): iterable
    {
        yield 'id inexistente' => [['5', '99']];
        yield 'nenhuma seleção' => [[]];
        yield 'valor escalar' => ['5'];
    }

    #[DataProvider('selecoesInvalidas')]
    public function testSelecaoInvalidaEhRecusadaComMensagemEmPortugues(mixed $selecao): void
    {
        $form = $this->formulario();
        $form->setData(['nome' => 'AR X', 'acN2s' => $selecao, 'situacao' => '4002']);

        self::assertFalse($form->isValid());
        self::assertSame(['Selecione ao menos uma AC N2 válida.'], array_values($form->getMessages()['acN2s']));
    }
}
