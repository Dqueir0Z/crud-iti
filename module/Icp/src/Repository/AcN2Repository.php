<?php

declare(strict_types=1);

namespace Icp\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Icp\Entity\AcN2;
use Icp\Enum\Situacao;

/**
 * @extends EntityRepository<AcN2>
 */
class AcN2Repository extends EntityRepository
{
    public function createListagemQuery(?string $busca, ?int $acId, ?Situacao $situacao): Query
    {
        $qb = $this->createQueryBuilder('n')
            ->innerJoin('n.ac', 'a')
            ->addSelect('a')
            ->orderBy('n.nome', 'ASC');

        if ($busca !== null && $busca !== '') {
            $qb->andWhere('n.nome LIKE :busca')->setParameter('busca', '%' . $busca . '%');
        }

        if ($acId !== null) {
            $qb->andWhere('a.id = :acId')->setParameter('acId', $acId);
        }

        if ($situacao !== null) {
            $qb->andWhere('n.situacao = :situacao')->setParameter('situacao', $situacao);
        }

        return $qb->getQuery();
    }

    /**
     * Todas as AC N2 com a AC carregada, ordenadas por AC e nome.
     *
     * @return list<AcN2>
     */
    public function findAllComAc(): array
    {
        return $this->createQueryBuilder('n')
            ->innerJoin('n.ac', 'a')
            ->addSelect('a')
            ->orderBy('a.nome', 'ASC')
            ->addOrderBy('n.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $ids
     * @return list<AcN2>
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->findBy(['id' => $ids]);
    }

    /**
     * Registros já importados do ITI, indexados pelo id do ITI.
     *
     * @return array<int, AcN2>
     */
    public function indexarPorItiId(): array
    {
        return $this->createQueryBuilder('n', 'n.itiId')
            ->where('n.itiId IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}
