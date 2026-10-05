<?php

declare(strict_types=1);

namespace Icp\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Icp\Entity\AcN2;
use Icp\Entity\Ar;
use Icp\Entity\VinculoArAcN2;
use Icp\Enum\Situacao;

/**
 * @extends EntityRepository<Ar>
 */
class ArRepository extends EntityRepository
{
    /**
     * Listagem com os vínculos e as AC N2 já carregados. O filtro por AC N2 usa
     * um join separado para não esconder os demais vínculos da AR na exibição.
     */
    public function createListagemQuery(?string $busca, ?int $acN2Id, ?Situacao $situacao): Query
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.vinculos', 'v')
            ->addSelect('v')
            ->leftJoin('v.acN2', 'n')
            ->addSelect('n')
            ->orderBy('r.nome', 'ASC');

        if ($busca !== null && $busca !== '') {
            $qb->andWhere('r.nome LIKE :busca')->setParameter('busca', '%' . $busca . '%');
        }

        if ($acN2Id !== null) {
            $qb->innerJoin('r.vinculos', 'filtro', 'WITH', 'IDENTITY(filtro.acN2) = :acN2Id')
                ->setParameter('acN2Id', $acN2Id);
        }

        if ($situacao !== null) {
            $qb->andWhere('r.situacao = :situacao')->setParameter('situacao', $situacao);
        }

        return $qb->getQuery();
    }

    /**
     * Vínculos (AC N2, AR, situação do vínculo) para montar a árvore da
     * estrutura sem hidratar entidades.
     *
     * @return list<array{acN2Id: int, id: int, nome: string, situacao: Situacao|int}>
     */
    public function findVinculosParaArvore(): array
    {
        return $this->getEntityManager()
            ->createQuery(
                'SELECT IDENTITY(v.acN2) AS acN2Id, r.id, r.nome, v.situacao
                 FROM ' . VinculoArAcN2::class . ' v
                 JOIN v.ar r
                 ORDER BY r.nome ASC'
            )
            ->getArrayResult();
    }

    /**
     * Vínculos de uma AC N2 com a AR carregada, em ordem alfabética da AR.
     *
     * @return list<VinculoArAcN2>
     */
    public function findVinculosDaAcN2(AcN2 $acN2, int $limite): array
    {
        return $this->getEntityManager()
            ->createQuery(
                'SELECT v, r
                 FROM ' . VinculoArAcN2::class . ' v
                 JOIN v.ar r
                 WHERE v.acN2 = :acN2
                 ORDER BY r.nome ASC'
            )
            ->setParameter('acN2', $acN2)
            ->setMaxResults($limite)
            ->getResult();
    }

    public function contarVinculos(): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(r.id) FROM ' . VinculoArAcN2::class . ' v JOIN v.ar r')
            ->getSingleScalarResult();
    }

    /**
     * ARs já importadas do ITI, indexadas pelo id do ITI e com os vínculos
     * carregados (evita uma consulta por AR durante a importação).
     *
     * @return array<int, Ar>
     */
    public function indexarPorItiIdComVinculos(): array
    {
        return $this->createQueryBuilder('r', 'r.itiId')
            ->leftJoin('r.vinculos', 'v')
            ->addSelect('v')
            ->leftJoin('v.acN2', 'n')
            ->addSelect('n')
            ->where('r.itiId IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}
