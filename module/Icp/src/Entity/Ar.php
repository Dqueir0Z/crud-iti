<?php

declare(strict_types=1);

namespace Icp\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Icp\Enum\Situacao;
use Icp\Repository\ArRepository;

/**
 * Autoridade de Registro (tipo "ar").
 *
 * No structure.json do ITI a mesma AR aparece sob várias AC N2, por isso o
 * vínculo é N:N (tabela ar_ac_n2). Toda AR deve ter ao menos uma AC N2.
 */
#[ORM\Entity(repositoryClass: ArRepository::class)]
#[ORM\Table(name: 'ar')]
#[ORM\HasLifecycleCallbacks]
class Ar
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
    #[ORM\ManyToMany(targetEntity: AcN2::class, inversedBy: 'ars')]
    #[ORM\JoinTable(name: 'ar_ac_n2')]
    #[ORM\JoinColumn(name: 'ar_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'ac_n2_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
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

    /** Vincula a AC N2; retorna false se o vínculo já existia. */
    public function vincularAcN2(AcN2 $acN2): bool
    {
        if ($this->acN2s->contains($acN2)) {
            return false;
        }

        $this->acN2s->add($acN2);

        return true;
    }

    /**
     * Substitui os vínculos pelos informados (usado no formulário de edição).
     *
     * @param iterable<AcN2> $acN2s
     */
    public function definirAcN2s(iterable $acN2s): void
    {
        $novas = [];
        foreach ($acN2s as $acN2) {
            $novas[spl_object_id($acN2)] = $acN2;
        }

        foreach ($this->acN2s->toArray() as $atual) {
            if (! isset($novas[spl_object_id($atual)])) {
                $this->acN2s->removeElement($atual);
            }
        }

        foreach ($novas as $acN2) {
            $this->vincularAcN2($acN2);
        }
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
