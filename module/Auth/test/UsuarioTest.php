<?php

declare(strict_types=1);

namespace AuthTest;

use Auth\Entity\Usuario;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Regressão (revisão do Codex): o bcrypt ignora o que passa de 72 bytes, então
 * uma senha maior aceitaria login com qualquer sufixo diferente.
 */
final class UsuarioTest extends TestCase
{
    public function testSenhaNoLimiteDoBcryptEhAceitaInteira(): void
    {
        $senha   = str_repeat('a', Usuario::TAMANHO_MAXIMO_SENHA_BYTES);
        $usuario = new Usuario('pessoa@exemplo.test', 'Pessoa', $senha);

        self::assertTrue($usuario->verificarSenha($senha));
        self::assertFalse($usuario->verificarSenha(str_repeat('a', Usuario::TAMANHO_MAXIMO_SENHA_BYTES - 1) . 'b'));
    }

    public function testSenhaAcimaDoLimiteEhRecusadaNoCadastro(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Usuario('pessoa@exemplo.test', 'Pessoa', str_repeat('a', Usuario::TAMANHO_MAXIMO_SENHA_BYTES) . 'A');
    }

    public function testLimiteEhEmBytesNaoEmCaracteres(): void
    {
        // 37 "é" = 74 bytes em UTF-8, embora sejam só 37 caracteres.
        $this->expectException(InvalidArgumentException::class);

        (new Usuario('pessoa@exemplo.test', 'Pessoa', 'senha-valida-123'))->definirSenha(str_repeat('é', 37));
    }
}
