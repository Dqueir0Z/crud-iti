<?php

declare(strict_types=1);

namespace AuthTest;

use Auth\Entity\Usuario;
use Auth\Service\CredenciaisAdapter;
use Doctrine\Persistence\ObjectRepository;
use Laminas\Authentication\Result;
use PHPUnit\Framework\TestCase;

final class CredenciaisAdapterTest extends TestCase
{
    private const EMAIL = 'pessoa@exemplo.test';
    private const SENHA = 'senha-de-teste-123';

    /** @return ObjectRepository<Usuario> */
    private function repositorioCom(?Usuario $usuario): ObjectRepository
    {
        $repositorio = $this->createMock(ObjectRepository::class);
        $repositorio->method('findOneBy')
            ->with(['email' => self::EMAIL])
            ->willReturn($usuario);

        return $repositorio;
    }

    public function testCredenciaisCorretasAutenticam(): void
    {
        $usuario = new Usuario(self::EMAIL, 'Pessoa', self::SENHA);

        // E-mail com maiúsculas/espaços deve ser normalizado antes da busca.
        $resultado = (new CredenciaisAdapter($this->repositorioCom($usuario), '  Pessoa@Exemplo.TEST ', self::SENHA))
            ->authenticate();

        self::assertTrue($resultado->isValid());
        self::assertSame(self::EMAIL, $resultado->getIdentity()['email']);
        self::assertSame('Pessoa', $resultado->getIdentity()['nome']);
    }

    public function testSenhaErradaFalha(): void
    {
        $usuario = new Usuario(self::EMAIL, 'Pessoa', self::SENHA);

        $resultado = (new CredenciaisAdapter($this->repositorioCom($usuario), self::EMAIL, 'outra-senha'))
            ->authenticate();

        self::assertSame(Result::FAILURE_CREDENTIAL_INVALID, $resultado->getCode());
        self::assertSame([CredenciaisAdapter::MENSAGEM_FALHA], $resultado->getMessages());
    }

    public function testUsuarioInexistenteFalhaComAMesmaMensagem(): void
    {
        $resultado = (new CredenciaisAdapter($this->repositorioCom(null), self::EMAIL, self::SENHA))
            ->authenticate();

        self::assertFalse($resultado->isValid());
        self::assertSame([CredenciaisAdapter::MENSAGEM_FALHA], $resultado->getMessages());
    }

    public function testSenhaNaoEhGuardadaEmTextoPuro(): void
    {
        $usuario = new Usuario(self::EMAIL, 'Pessoa', self::SENHA);

        self::assertTrue($usuario->verificarSenha(self::SENHA));
        self::assertFalse($usuario->verificarSenha(strtoupper(self::SENHA)));
        self::assertFalse($usuario->precisaRehash());
    }
}
