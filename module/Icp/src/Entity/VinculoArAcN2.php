<?php

declare(strict_types=1);

namespace Icp\Entity;

use Doctrine\ORM\Mapping as ORM;
use Icp\Enum\Situacao;

/**
 * Vínculo AR ↔ AC N2 (tabela ar_ac_n2).
 *
 * No structure.json a mesma AR pode estar credenciada por uma AC N2 e em
 * credenciamento por outra, por isso a situação fica no vínculo. A chave é o
 * par (AR, AC N2); o vínculo pertence à AR (Ar::$vinculos com orphanRemoval).
 *
 * O lado da AC N2 não tem cascade: excluir uma AC N2 que ainda tem vínculos
 * esbarra no FOREIGN KEY ... ON DELETE RESTRICT do banco.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ar_ac_n2')]
class VinculoArAcN2
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Ar::class, inversedBy: 'vinculos')]
    #[ORM\JoinColumn(name: 'ar_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Ar $ar;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: AcN2::class, inversedBy: 'vinculos')]
    #[ORM\JoinColumn(name: 'ac_n2_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private AcN2 $acN2;

    #[ORM\Column(type: 'smallint', enumType: Situacao::class, options: ['default' => 4002])]
    private Situacao $situacao;

    public function __construct(Ar $ar, AcN2 $acN2, Situacao $situacao)
    {
        $this->ar       = $ar;
        $this->acN2     = $acN2;
        $this->situacao = $situacao;
    }

    public function getAr(): Ar
    {
        return $this->ar;
    }

    public function getAcN2(): AcN2
    {
        return $this->acN2;
    }

    public function getSituacao(): Situacao
    {
        return $this->situacao;
    }

    /** Retorna true se a situação mudou. */
    public function definirSituacao(Situacao $situacao): bool
    {
        if ($this->situacao === $situacao) {
            return false;
        }

        $this->situacao = $situacao;

        return true;
    }
}
