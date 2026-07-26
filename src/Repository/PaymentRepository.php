<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Payment::class); }

    public function sumValidated(): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.totalAmount), 0)')
            ->andWhere('p.status != :cancelled')
            ->setParameter('cancelled', Payment::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
