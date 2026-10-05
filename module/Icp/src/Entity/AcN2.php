<?php

declare(strict_types=1);

namespace Icp\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Icp\Enum\Situacao;
use Icp\Repository\AcN2Repository;

/**
 * Autoridade Certificadora de 2º nível (tipo "ac-2"), subordinada a uma AC.
 */
#[ORM\Entity(repositoryClass: AcN2Repository::class)]
#[ORM\Table(name: 'ac_n2')]
#[ORM\HasLifecycleCallbacks]
class AcN2
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

    #[ORM\ManyToOne(targetEntity: Ac::class, inversedBy: 'acN2s')]
    #[ORM\JoinColumn(name: 'ac_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Ac $ac;

    /**
     * Lado inverso do vínculo N:N com AR (o lado dono é Ar::$acN2s).
     *
     * @var Collection<int, Ar>
     */
    #[ORM\ManyToMany(targetEntity: Ar::class, mappedBy: 'acN2s', fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['nome' => 'ASC'])]
    private Collection $ars;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $nome,
        Ac $ac,
        Situacao $situacao = Situacao::Credenciado,
        ?int $itiId = null
    ) {
        $this->nome     = $nome;
        $this->ac       = $ac;
        $this->situacao = $situacao;
        $this->itiId    = $itiId;
        $this->ars      = new ArrayCollection();

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

    public function getAc(): Ac
    {
        return $this->ac;
    }

    public function setAc(Ac $ac): void
    {
        $this->ac = $ac;
    }

    /** @return Collection<int, Ar> */
    public function getArs(): Collection
    {
        return $this->ars;
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
