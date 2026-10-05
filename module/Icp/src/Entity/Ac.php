<?php

declare(strict_types=1);

namespace Icp\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Icp\Enum\Situacao;
use Icp\Repository\AcRepository;

/**
 * Autoridade Certificadora de 1º nível (tipo "ac-1" no structure.json do ITI).
 */
#[ORM\Entity(repositoryClass: AcRepository::class)]
#[ORM\Table(name: 'ac')]
#[ORM\HasLifecycleCallbacks]
class Ac
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** Identificador da entidade no ITI; nulo para registros cadastrados manualmente. */
    #[ORM\Column(name: 'iti_id', type: 'integer', nullable: true, unique: true)]
    private ?int $itiId;

    #[ORM\Column(type: 'string', length: 150)]
    private string $nome;

    #[ORM\Column(type: 'smallint', enumType: Situacao::class, options: ['default' => 4002])]
    private Situacao $situacao;

    /** @var Collection<int, AcN2> */
    #[ORM\OneToMany(mappedBy: 'ac', targetEntity: AcN2::class, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['nome' => 'ASC'])]
    private Collection $acN2s;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $nome,
        Situacao $situacao = Situacao::Credenciado,
        ?int $itiId = null
    ) {
        $this->nome     = $nome;
        $this->situacao = $situacao;
        $this->itiId    = $itiId;
        $this->acN2s    = new ArrayCollection();

        $agora = new DateTimeImmutable();

        $this->createdAt = $agora;
        $this->updatedAt = $agora;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItiId(): ?int
    {
        return $this->itiId;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): void
    {
        $this->nome = $nome;
    }

    public function getSituacao(): Situacao
    {
        return $this->situacao;
    }

    public function setSituacao(Situacao $situacao): void
    {
        $this->situacao = $situacao;
    }

    /** @return Collection<int, AcN2> */
    public function getAcN2s(): Collection
    {
        return $this->acN2s;
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
