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

    /**
     * Encaisse et nombre d'operations sur une periode.
     *
     * On somme totalAmount plutot que le detail des affectations : depuis que
     * tout excedent non affectable est refuse, les deux sont egaux et cette
     * lecture ne coute qu'une requete.
     *
     * @return array{count: int, total: float}
     */
    public function summaryBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $row = $this->createQueryBuilder('p')
            ->select('COUNT(p.id) AS nb', 'COALESCE(SUM(p.totalAmount), 0) AS total')
            ->andWhere('p.status != :cancelled')
            ->andWhere('p.paymentDate >= :from')
            ->andWhere('p.paymentDate < :to')
            ->setParameter('cancelled', Payment::STATUS_CANCELLED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleResult();

        return ['count' => (int) $row['nb'], 'total' => (float) $row['total']];
    }

    /**
     * Repartition de l'encaisse par mode de paiement.
     *
     * @return array<string, float>
     */
    public function sumByMethod(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.paymentMethod AS method', 'COALESCE(SUM(p.totalAmount), 0) AS total')
            ->andWhere('p.status != :cancelled')
            ->setParameter('cancelled', Payment::STATUS_CANCELLED)
            ->groupBy('p.paymentMethod')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_combine(
            array_column($rows, 'method'),
            array_map('floatval', array_column($rows, 'total'))
        );
    }

    /**
     * Recettes par classe, puis par niveau.
     *
     * @return array<string, float>
     */
    public function sumByStudentField(string $field): array
    {
        if (!in_array($field, ['name', 'level'], true)) {
            throw new \InvalidArgumentException('Regroupement non supporte : ' . $field);
        }

        $rows = $this->createQueryBuilder('p')
            ->select('c.' . $field . ' AS label', 'COALESCE(SUM(p.totalAmount), 0) AS total')
            ->join('p.student', 's')
            ->join('s.schoolClass', 'c')
            ->andWhere('p.status != :cancelled')
            ->setParameter('cancelled', Payment::STATUS_CANCELLED)
            ->groupBy('c.' . $field)
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_combine(
            array_column($rows, 'label'),
            array_map('floatval', array_column($rows, 'total'))
        );
    }

    /**
     * Serie temporelle des recettes, en remplissant les periodes sans paiement
     * pour que le graphique ne saute pas de trous.
     *
     * Passe par du SQL natif : DQL n'offre pas de troncature de date. Le projet
     * cible PostgreSQL, conformement au cahier des charges.
     *
     * @return array<string, float> libelle de periode => montant
     */
    public function revenueSeries(string $granularity, int $periods): array
    {
        [$sqlFormat, $truncUnit, $phpFormat, $intervalUnit] = match ($granularity) {
            'day' => ['YYYY-MM-DD', 'day', 'Y-m-d', 'D'],
            'month' => ['YYYY-MM', 'month', 'Y-m', 'M'],
            default => throw new \InvalidArgumentException('Granularite non supportee : ' . $granularity),
        };

        $first = (new \DateTimeImmutable($granularity === 'month' ? 'first day of this month' : 'today'))
            ->sub(new \DateInterval('P' . ($periods - 1) . $intervalUnit));

        // Le format et l'unite proviennent du match ci-dessus, jamais d'une
        // entree utilisateur : leur interpolation est sans risque. Seules les
        // valeurs restent des parametres lies.
        $sql = sprintf(
            "SELECT to_char(date_trunc('%s', payment_date), '%s') AS bucket,
                    COALESCE(SUM(total_amount), 0) AS total
             FROM payment
             WHERE status != :cancelled AND payment_date >= :first
             GROUP BY bucket
             ORDER BY bucket",
            $truncUnit,
            $sqlFormat
        );

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, [
            'cancelled' => Payment::STATUS_CANCELLED,
            'first' => $first->format('Y-m-d H:i:s'),
        ]);

        $totals = array_combine(
            array_column($rows, 'bucket'),
            array_map('floatval', array_column($rows, 'total'))
        );

        // Les periodes sans paiement sont remises a zero : sans cela le
        // graphique sauterait les jours creux et deformerait la lecture.
        $series = [];
        for ($i = $periods - 1; $i >= 0; --$i) {
            $key = $first->add(new \DateInterval('P' . ($periods - 1 - $i) . $intervalUnit))->format($phpFormat);
            $series[$key] = $totals[$key] ?? 0.0;
        }

        return $series;
    }
}
