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

    /**
     * Encaissements ventiles par type de frais.
     *
     * Passe par le detail des affectations et non par le montant du paiement :
     * un versement unique peut couvrir plusieurs types de frais, et seul le
     * detail sait ce qui est alle sur quoi.
     *
     * @return array<int, array{label: string, count: int, total: float}>
     */
    public function reportByFeeType(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('ft.name AS label', 'COUNT(d.id) AS nb', 'COALESCE(SUM(d.amount), 0) AS total')
            ->join('d.payment', 'p')
            ->join('d.feeAssignment', 'f')
            ->join('f.feeType', 'ft')
            ->andWhere('p.status != :cancelled')
            ->andWhere('p.paymentDate >= :from')
            ->andWhere('p.paymentDate < :to')
            ->setParameter('cancelled', Payment::STATUS_CANCELLED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('ft.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): array => [
                'label' => (string) $row['label'],
                'count' => (int) $row['nb'],
                'total' => (float) $row['total'],
            ],
            $rows
        );
    }
}
