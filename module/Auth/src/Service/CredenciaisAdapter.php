<?php

declare(strict_types=1);

namespace Auth\Service;

use Auth\Entity\Usuario;
use Doctrine\Persistence\ObjectRepository;
use Laminas\Authentication\Adapter\AdapterInterface;
use Laminas\Authentication\Result;

use function password_verify;

/**
 * Autentica por e-mail e senha contra a tabela de usuários.
 *
 * A identidade gravada na sessão é um array pequeno (id, e-mail e nome),
 * suficiente para o layout sem consultar o banco a cada requisição.
 */
final class CredenciaisAdapter implements AdapterInterface
{
    public const MENSAGEM_FALHA = 'E-mail ou senha inválidos.';

    /** Hash de uma senha aleatória descartada; só serve para igualar o tempo de resposta. */
    private const HASH_FICTICIO = '$2y$10$vXgfzkkjEdB0GGzUb1t.CuM6iXFj7DkjfOfQV4tPP8R8Hh5FPraha';

    /** @param ObjectRepository<Usuario> $usuarios */
    public function __construct(
        private ObjectRepository $usuarios,
        private string $email,
        private string $senha
    ) {
    }

    public function authenticate(): Result
    {
        $usuario = $this->usuarios->findOneBy(['email' => Usuario::normalizarEmail($this->email)]);

        if (! $usuario instanceof Usuario) {
            // Mantém tempo de resposta semelhante ao de uma senha errada,
            // para não revelar quais e-mails existem.
            password_verify($this->senha, self::HASH_FICTICIO);

            return new Result(Result::FAILURE_IDENTITY_NOT_FOUND, null, [self::MENSAGEM_FALHA]);
        }

        if (! $usuario->verificarSenha($this->senha)) {
            return new Result(Result::FAILURE_CREDENTIAL_INVALID, null, [self::MENSAGEM_FALHA]);
        }

        return new Result(Result::SUCCESS, [
            'id'    => $usuario->getId(),
            'email' => $usuario->getEmail(),
            'nome'  => $usuario->getNome(),
        ]);
    }
}
