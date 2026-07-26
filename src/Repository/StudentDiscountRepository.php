<?php

namespace App\Repository;

use App\Entity\StudentDiscount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StudentDiscountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, StudentDiscount::class); }

    /**
     * Reductions accordees sur une periode, pour le rapport des reductions.
     *
     * @return StudentDiscount[]
     */
    public function grantedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('sd')
            ->leftJoin('sd.student', 's')->addSelect('s')
            ->leftJoin('sd.discount', 'd')->addSelect('d')
            ->leftJoin('sd.approvedBy', 'u')->addSelect('u')
            ->andWhere('sd.createdAt >= :from')
            ->andWhere('sd.createdAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('sd.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
