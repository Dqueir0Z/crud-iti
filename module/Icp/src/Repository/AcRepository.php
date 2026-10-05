<?php

declare(strict_types=1);

namespace Icp\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Icp\Entity\Ac;
use Icp\Enum\Situacao;

/**
 * @extends EntityRepository<Ac>
 */
class AcRepository extends EntityRepository
{
    public function createListagemQuery(?string $busca, ?Situacao $situacao): Query
    {
        $qb = $this->createQueryBuilder('a')->orderBy('a.nome', 'ASC');

        if ($busca !== null && $busca !== '') {
            $qb->andWhere('a.nome LIKE :busca')->setParameter('busca', '%' . $busca . '%');
        }

        if ($situacao !== null) {
            $qb->andWhere('a.situacao = :situacao')->setParameter('situacao', $situacao);
        }

        return $qb->getQuery();
    }

    /** @return list<Ac> */
    public function findAllOrdenadas(): array
    {
        return $this->findBy([], ['nome' => 'ASC']);
    }

    /**
     * Registros já importados do ITI, indexados pelo id do ITI.
     *
     * @return array<int, Ac>
     */
    public function indexarPorItiId(): array
    {
        return $this->createQueryBuilder('a', 'a.itiId')
            ->where('a.itiId IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}
