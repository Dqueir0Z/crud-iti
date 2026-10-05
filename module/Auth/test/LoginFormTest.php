<?php

declare(strict_types=1);

namespace AuthTest;

use Auth\Form\LoginForm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regressão (revisão final do Codex): senha enviada como lista (senha[]=x)
 * passava pelo NotEmpty e derrubava o login com TypeError (HTTP 500).
 */
final class LoginFormTest extends TestCase
{
    private function formulario(): LoginForm
    {
        $form = new LoginForm();
        $form->remove('csrf');

        return $form;
    }

    public function testCredenciaisEmTextoSaoAceitas(): void
    {
        $form = $this->formulario();
        $form->setData(['email' => ' demo@crud-iti.test ', 'senha' => 'qualquer-coisa']);

        self::assertTrue($form->isValid(), json_encode($form->getMessages()) ?: '');
        self::assertSame('demo@crud-iti.test', $form->getData()['email']);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function camposEmLista(): iterable
    {
        yield 'senha como lista' => [['email' => 'demo@crud-iti.test', 'senha' => ['x']], 'senha'];
        yield 'e-mail como lista' => [['email' => ['x'], 'senha' => 'y'], 'email'];
    }

    /** @param array<string, mixed> $dados */
    #[DataProvider('camposEmLista')]
    public function testCampoEnviadoComoListaEhRecusadoSemErro(array $dados, string $campo): void
    {
        $form = $this->formulario();
        $form->setData($dados);

        self::assertFalse($form->isValid());
        self::assertArrayHasKey($campo, $form->getMessages());
    }
}
