<?php

declare(strict_types=1);

namespace Auth\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

use function mb_strtolower;
use function password_hash;
use function password_needs_rehash;
use function password_verify;
use function trim;

use const PASSWORD_DEFAULT;

/**
 * Usuário que acessa o sistema. A senha é guardada apenas como hash.
 */
#[ORM\Entity]
#[ORM\Table(name: 'usuario')]
class Usuario
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 120)]
    private string $nome;

    #[ORM\Column(name: 'senha_hash', type: 'string', length: 255)]
    private string $senhaHash;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(string $email, string $nome, string $senha)
    {
        $this->email     = self::normalizarEmail($email);
        $this->nome      = $nome;
        $this->senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $this->createdAt = new DateTimeImmutable();
    }

    public static function normalizarEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function verificarSenha(string $senha): bool
    {
        return password_verify($senha, $this->senhaHash);
    }

    public function definirSenha(string $senha): void
    {
        $this->senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    }

    /** Indica se o hash foi gerado com um algoritmo/custo desatualizado. */
    public function precisaRehash(): bool
    {
        return password_needs_rehash($this->senhaHash, PASSWORD_DEFAULT);
    }
}
