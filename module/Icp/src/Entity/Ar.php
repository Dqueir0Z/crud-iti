<?php

declare(strict_types=1);

namespace Icp\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Icp\Enum\Situacao;
use Icp\Repository\ArRepository;

use function array_map;
use function count;
use function spl_object_id;
use function strcmp;
use function usort;

/**
 * Autoridade de Registro (tipo "ar").
 *
 * No structure.json do ITI a mesma AR aparece sob várias AC N2, por isso o
 * vínculo é N:N (tabela ar_ac_n2), e a situação é registrada por vínculo
 * (VinculoArAcN2). Toda AR deve ter ao menos uma AC N2.
 *
 * A coluna situacao da AR é a situação geral, usada na listagem e no filtro:
 * Credenciado se ao menos um vínculo estiver credenciado.
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

    /** @var Collection<int, VinculoArAcN2> */
    #[ORM\OneToMany(targetEntity: VinculoArAcN2::class, mappedBy: 'ar', cascade: ['persist'], orphanRemoval: true)]
    private Collection $vinculos;

    /**
     * Vínculos retirados antes do flush. Se o mesmo par voltar, a instância é
     * reaproveitada: criar outra com a mesma chave faria o INSERT ocorrer antes
     * do DELETE do orphanRemoval e violaria a chave primária. Não é persistido.
     *
     * @var array<int, VinculoArAcN2> indexado por spl_object_id da AC N2
     */
    private array $vinculosRetirados = [];

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
        $this->vinculos = new ArrayCollection();

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

    /** Situação geral da AR (ver regra no docblock da classe). */
    public function getSituacao(): Situacao
    {
        return $this->situacao;
    }

    /** @return list<VinculoArAcN2> ordenados pelo nome da AC N2 */
    public function getVinculos(): array
    {
        $vinculos = $this->vinculos->getValues();
        usort(
            $vinculos,
            static fn (VinculoArAcN2 $a, VinculoArAcN2 $b): int => strcmp($a->getAcN2()->getNome(), $b->getAcN2()->getNome())
        );

        return $vinculos;
    }

    /** @return list<AcN2> ordenadas pelo nome */
    public function getAcN2s(): array
    {
        return array_map(static fn (VinculoArAcN2 $v): AcN2 => $v->getAcN2(), $this->getVinculos());
    }

    /** Há vínculos com situações diferentes (só acontece por importação). */
    public function temSituacoesDiferentes(): bool
    {
        $situacoes = [];
        foreach ($this->vinculos as $vinculo) {
            $situacoes[$vinculo->getSituacao()->value] = true;
        }

        return count($situacoes) > 1;
    }

    /**
     * Vincula a AC N2; retorna false se o vínculo já existia.
     * Sem situação informada, o vínculo recebe a situação geral da AR.
     */
    public function vincularAcN2(AcN2 $acN2, ?Situacao $situacao = null): bool
    {
        if ($this->vinculoCom($acN2) !== null) {
            return false;
        }

        $chave   = spl_object_id($acN2);
        $vinculo = $this->vinculosRetirados[$chave] ?? new VinculoArAcN2($this, $acN2, $situacao ?? $this->situacao);
        unset($this->vinculosRetirados[$chave]);

        if ($situacao !== null) {
            $vinculo->definirSituacao($situacao);
        }

        $this->vinculos->add($vinculo);
        $acN2->adicionarVinculo($vinculo);
        $this->marcarAlteracao();

        return true;
    }

    /**
     * Substitui as AC N2 vinculadas pelas informadas (formulário). Trabalha por
     * diferença: vínculos que continuam mantêm a instância e a situação; os
     * novos recebem a situação geral da AR.
     *
     * @param iterable<AcN2> $acN2s
     */
    public function definirAcN2s(iterable $acN2s): void
    {
        $novas = [];
        foreach ($acN2s as $acN2) {
            $novas[spl_object_id($acN2)] = $acN2;
        }

        foreach ($this->vinculos->toArray() as $vinculo) {
            if (! isset($novas[spl_object_id($vinculo->getAcN2())])) {
                $this->retirarVinculo($vinculo);
            }
        }

        foreach ($novas as $acN2) {
            $this->vincularAcN2($acN2);
        }
    }

    /**
     * Ajusta a situação do vínculo com a AC N2 (importação).
     * Retorna true se mudou; false se não mudou ou se o vínculo não existe.
     */
    public function definirSituacaoDoVinculo(AcN2 $acN2, Situacao $situacao): bool
    {
        $vinculo = $this->vinculoCom($acN2);
        if ($vinculo === null || ! $vinculo->definirSituacao($situacao)) {
            return false;
        }

        $this->marcarAlteracao();

        return true;
    }

    /**
     * Aplica a situação à AR e a todos os vínculos (edição manual em que o
     * usuário alterou o campo situação).
     */
    public function aplicarSituacaoATodos(Situacao $situacao): void
    {
        $this->situacao = $situacao;
        foreach ($this->vinculos as $vinculo) {
            $vinculo->definirSituacao($situacao);
        }
        $this->marcarAlteracao();
    }

    /**
     * Recalcula a situação geral a partir dos vínculos: Credenciado se algum
     * vínculo estiver credenciado; senão, Em credenciamento. Sem vínculos,
     * mantém a atual. Retorna true se mudou.
     */
    public function recalcularSituacao(): bool
    {
        if ($this->vinculos->isEmpty()) {
            return false;
        }

        $geral = Situacao::EmCredenciamento;
        foreach ($this->vinculos as $vinculo) {
            if ($vinculo->getSituacao() === Situacao::Credenciado) {
                $geral = Situacao::Credenciado;
                break;
            }
        }

        if ($geral === $this->situacao) {
            return false;
        }

        $this->situacao = $geral;

        return true;
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

    /**
     * Depois de gravada, a AR esquece os vínculos retirados: devolver a mesma
     * AC N2 num flush seguinte cria um vínculo novo, com a situação geral atual.
     * (Retirar um vínculo marca a AR como alterada, então o PostUpdate dispara.)
     */
    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    public function esquecerVinculosRetirados(): void
    {
        $this->vinculosRetirados = [];
    }

    /**
     * Aplica a edição manual do formulário. Só uma mudança explícita do campo
     * situação vale para todos os vínculos; editar o nome ou as AC N2 preserva a
     * situação de cada vínculo e recalcula a geral.
     *
     * @param iterable<AcN2> $acN2s
     */
    public function aplicarEdicao(string $nome, iterable $acN2s, Situacao $situacao): void
    {
        $alterouSituacao = $situacao !== $this->situacao;

        $this->nome = $nome;
        $this->definirAcN2s($acN2s);

        if ($alterouSituacao) {
            $this->aplicarSituacaoATodos($situacao);
        } else {
            $this->recalcularSituacao();
        }
    }

    private function vinculoCom(AcN2 $acN2): ?VinculoArAcN2
    {
        foreach ($this->vinculos as $vinculo) {
            if ($vinculo->getAcN2() === $acN2) {
                return $vinculo;
            }
        }

        return null;
    }

    private function retirarVinculo(VinculoArAcN2 $vinculo): void
    {
        $this->vinculos->removeElement($vinculo);
        $vinculo->getAcN2()->retirarVinculo($vinculo);
        $this->vinculosRetirados[spl_object_id($vinculo->getAcN2())] = $vinculo;
        $this->marcarAlteracao();
    }

    /**
     * Mudanças só nos vínculos não alteram colunas da AR, e o PreUpdate não
     * dispararia; atualizar a data aqui registra a alteração na própria AR.
     */
    private function marcarAlteracao(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
