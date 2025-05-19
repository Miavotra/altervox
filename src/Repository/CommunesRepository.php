<?php

namespace App\Repository;

use App\Entity\Communes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Communes>
 */
class CommunesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Communes::class);
    }

    /**
    * @return Communes Returns an array of CommandeProduit objects
    */
    public function findByInsee($value): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.code_insee = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getResult()
        ;
    }

    //    /**
    //     * @return Communes[] Returns an array of Communes objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Communes
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
