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
     * Encaissements agreges par periode, pour les rapports journalier,
     * hebdomadaire, mensuel et annuel.
     *
     * SQL natif : DQL n'offre pas de troncature de date. L'unite provient d'une
     * liste fermee, jamais de l'URL.
     *
     * @return array<int, array{bucket: string, count: int, total: float}>
     */
    public function reportByPeriod(string $granularity, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        [$truncUnit, $sqlFormat] = match ($granularity) {
            'day' => ['day', 'DD/MM/YYYY'],
            'week' => ['week', '"S"IW YYYY'],
            'month' => ['month', 'MM/YYYY'],
            'year' => ['year', 'YYYY'],
            default => throw new \InvalidArgumentException('Granularite non supportee : ' . $granularity),
        };

        $sql = sprintf(
            "SELECT to_char(date_trunc('%s', payment_date), '%s') AS bucket,
                    COUNT(*) AS nb,
                    COALESCE(SUM(total_amount), 0) AS total
             FROM payment
             WHERE status != :cancelled AND payment_date >= :from AND payment_date < :to
             GROUP BY date_trunc('%s', payment_date)
             ORDER BY date_trunc('%s', payment_date)",
            $truncUnit,
            $sqlFormat,
            $truncUnit,
            $truncUnit
        );

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, [
            'cancelled' => Payment::STATUS_CANCELLED,
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ]);

        return array_map(
            static fn (array $row): array => [
                'bucket' => (string) $row['bucket'],
                'count' => (int) $row['nb'],
                'total' => (float) $row['total'],
            ],
            $rows
        );
    }

    /**
     * Encaissements agreges par dimension metier.
     *
     * Le regroupement par parent compte le paiement pour chaque responsable
     * legal de l'eleve : la somme des lignes peut donc depasser l'encaisse
     * reelle quand un enfant a deux parents. C'est voulu, le rapport repondant
     * a "combien a-t-on encaisse pour les enfants de ce parent".
     *
     * @return array<int, array{label: string, count: int, total: float}>
     */
    public function reportByDimension(string $dimension, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->andWhere('p.status != :cancelled')
            ->andWhere('p.paymentDate >= :from')
            ->andWhere('p.paymentDate < :to')
            ->setParameter('cancelled', Payment::STATUS_CANCELLED)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        match ($dimension) {
            'class' => $queryBuilder
                ->join('p.student', 's')->join('s.schoolClass', 'c')
                ->select('c.name AS label')->groupBy('c.name'),
            'level' => $queryBuilder
                ->join('p.student', 's')->join('s.schoolClass', 'c')
                ->select('c.level AS label')->groupBy('c.level'),
            'method' => $queryBuilder
                ->select('p.paymentMethod AS label')->groupBy('p.paymentMethod'),
            'accountant' => $queryBuilder
                ->join('p.receivedBy', 'u')
                ->select("CONCAT(u.firstName, ' ', u.lastName) AS label")->groupBy('u.id'),
            'student' => $queryBuilder
                ->join('p.student', 's')
                ->select("CONCAT(s.registrationNumber, ' - ', s.firstName, ' ', s.lastName) AS label")->groupBy('s.id'),
            'parent' => $queryBuilder
                ->join('p.student', 's')->join('s.parents', 'pg')
                ->select("CONCAT(pg.firstName, ' ', pg.lastName) AS label")->groupBy('pg.id'),
            default => throw new \InvalidArgumentException('Dimension non supportee : ' . $dimension),
        };

        $rows = $queryBuilder
            ->addSelect('COUNT(p.id) AS nb', 'COALESCE(SUM(p.totalAmount), 0) AS total')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): array => [
                'label' => (string) ($row['label'] ?? 'Non renseigne'),
                'count' => (int) $row['nb'],
                'total' => (float) $row['total'],
            ],
            $rows
        );
    }

    /**
     * Paiements annules sur la periode, pour le rapport des annulations.
     *
     * @return Payment[]
     */
    public function cancelledBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.student', 's')->addSelect('s')
            ->leftJoin('p.cancelledBy', 'u')->addSelect('u')
            ->andWhere('p.status = :cancelled')
            ->andWhere('p.cancelledAt >= :from')
            ->andWhere('p.cancelledAt < :to')
            ->setParameter('cancelled', Payment::STATUS_CANCELLED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('p.cancelledAt', 'DESC')
            ->getQuery()
            ->getResult();
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
