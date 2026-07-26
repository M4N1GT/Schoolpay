<?php

namespace App\Repository;

use App\Entity\FeeAssignment;
use App\Entity\Payment;
use App\Entity\PaymentDetail;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PaymentDetailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, PaymentDetail::class); }

    /**
     * Somme des versements non annules d'un eleve donne sur un frais donne.
     * Le filtre sur l'eleve est indispensable : un frais affecte a une classe
     * est partage par tous les eleves de cette classe.
     */
    public function sumPaidForStudent(FeeAssignment $feeAssignment, Student $student): float
    {
        return (float) $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.amount), 0)')
            ->join('d.payment', 'p')
            ->andWhere('d.feeAssignment = :fee')
            ->andWhere('p.student = :student')
            ->andWhere('p.status != :cancelled')
            ->setParameter('fee', $feeAssignment)
            ->setParameter('student', $student)
            ->setParameter('cancelled', Payment::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
