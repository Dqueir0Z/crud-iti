<?php

declare(strict_types=1);

namespace Icp\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ar')]
#[ORM\HasLifecycleCallbacks]
class Ar
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 150)]
    private string $nome;

    #[ORM\ManyToOne(targetEntity: AcN2::class)]
    #[ORM\JoinColumn(
        name: 'ac_n2_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT'
    )]
    private AcN2 $acN2;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $nome = '', ?AcN2 $acN2 = null)
    {
        $this->nome = $nome;

        if ($acN2 !== null) {
            $this->acN2 = $acN2;
        }

        $agora = new DateTimeImmutable();

        $this->createdAt = $agora;
        $this->updatedAt = $agora;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): void
    {
        $this->nome = $nome;
    }

    public function getAcN2(): AcN2
    {
        return $this->acN2;
    }

    public function setAcN2(AcN2 $acN2): void
    {
        $this->acN2 = $acN2;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function atualizarData(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
