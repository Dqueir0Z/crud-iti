<?php

declare(strict_types=1);

namespace Auth\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

use function mb_strtolower;
use function password_hash;
use function password_needs_rehash;
use function password_verify;
use function sprintf;
use function strlen;
use function trim;

use const PASSWORD_DEFAULT;

/**
 * Usuário que acessa o sistema. A senha é guardada apenas como hash.
 */
#[ORM\Entity]
#[ORM\Table(name: 'usuario')]
class Usuario
{
    /** O bcrypt (PASSWORD_DEFAULT no PHP 8.3) ignora o que passa de 72 bytes. */
    public const TAMANHO_MAXIMO_SENHA_BYTES = 72;

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
        $this->senhaHash = self::gerarHash($senha);
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
        $this->senhaHash = self::gerarHash($senha);
    }

    /**
     * Recusa senhas que o bcrypt truncaria: sem isso, só os primeiros 72 bytes
     * contariam e qualquer sufixo diferente também seria aceito no login.
     */
    private static function gerarHash(string $senha): string
    {
        if (strlen($senha) > self::TAMANHO_MAXIMO_SENHA_BYTES) {
            throw new InvalidArgumentException(sprintf(
                'A senha deve ter no máximo %d bytes (letras acentuadas ocupam 2).',
                self::TAMANHO_MAXIMO_SENHA_BYTES
            ));
        }

        return password_hash($senha, PASSWORD_DEFAULT);
    }

    /** Indica se o hash foi gerado com um algoritmo/custo desatualizado. */
    public function precisaRehash(): bool
    {
        return password_needs_rehash($this->senhaHash, PASSWORD_DEFAULT);
    }
}
