<?php

namespace App\Service;

use App\Entity\Discount;
use App\Repository\PaymentDetailRepository;
use App\Repository\PaymentRepository;
use App\Repository\StudentDiscountRepository;
use App\Repository\StudentRepository;

class ReportService
{
    /**
     * Catalogue des rapports (cahier des charges, section 19).
     *
     * Un registre plutot que quinze controleurs : tous partagent le meme
     * rendu, la meme impression et le meme export, seule la source de donnees
     * change.
     */
    private const REPORTS = [
        'journalier' => ['label' => 'Paiements par jour', 'kind' => 'period', 'arg' => 'day'],
        'hebdomadaire' => ['label' => 'Paiements par semaine', 'kind' => 'period', 'arg' => 'week'],
        'mensuel' => ['label' => 'Paiements par mois', 'kind' => 'period', 'arg' => 'month'],
        'annuel' => ['label' => 'Paiements par annee', 'kind' => 'period', 'arg' => 'year'],
        'par-classe' => ['label' => 'Recettes par classe', 'kind' => 'dimension', 'arg' => 'class'],
        'par-niveau' => ['label' => 'Recettes par niveau', 'kind' => 'dimension', 'arg' => 'level'],
        'par-eleve' => ['label' => 'Recettes par eleve', 'kind' => 'dimension', 'arg' => 'student'],
        'par-parent' => ['label' => 'Recettes par parent', 'kind' => 'dimension', 'arg' => 'parent'],
        'par-mode' => ['label' => 'Recettes par mode de paiement', 'kind' => 'dimension', 'arg' => 'method'],
        'par-comptable' => ['label' => 'Recettes par comptable', 'kind' => 'dimension', 'arg' => 'accountant'],
        'par-type-de-frais' => ['label' => 'Recettes par type de frais', 'kind' => 'feeType', 'arg' => null],
        'reductions' => ['label' => 'Reductions accordees', 'kind' => 'discounts', 'arg' => null],
        'annulations' => ['label' => 'Paiements annules', 'kind' => 'cancellations', 'arg' => null],
        'impayes' => ['label' => 'Impayes par eleve', 'kind' => 'outstanding', 'arg' => null],
        'recouvrement' => ['label' => 'Taux de recouvrement', 'kind' => 'collection', 'arg' => null],
    ];

    public function __construct(
        private StudentRepository $students,
        private PaymentCalculationService $calculator,
        private PaymentRepository $payments,
        private PaymentDetailRepository $paymentDetails,
        private StudentDiscountRepository $studentDiscounts,
    ) {
    }

    /**
     * Le montant attendu se calcule eleve par eleve : un frais affecte a une
     * classe est du par chaque eleve de cette classe, et les reductions sont
     * individuelles. Sommer les FeeAssignment une seule fois sous-estimerait
     * fortement le montant attendu.
     */
    public function overview(): array
    {
        $expected = 0.0;
        $paid = 0.0;
        $unpaidStudents = 0;

        foreach ($this->students->findAll() as $student) {
            $situation = $this->calculator->getStudentSituation($student);
            $expected += $situation['netTotal'];
            $paid += $situation['paidTotal'];
            if ($situation['remainingTotal'] > 0) {
                ++$unpaidStudents;
            }
        }

        return [
            'students' => $this->students->count([]),
            'expected' => $expected,
            'paid' => $paid,
            'remaining' => max(0.0, $expected - $paid),
            'collectionRate' => $expected > 0 ? round(($paid / $expected) * 100, 1) : 0,
            'unpaidStudents' => $unpaidStudents,
        ];
    }

    /** @return array<string, string> cle => libelle */
    public function catalogue(): array
    {
        return array_map(static fn (array $report): string => $report['label'], self::REPORTS);
    }

    public function has(string $key): bool
    {
        return isset(self::REPORTS[$key]);
    }

    public function run(string $key, \DateTimeImmutable $from, \DateTimeImmutable $to): ReportResult
    {
        $report = self::REPORTS[$key] ?? throw new \InvalidArgumentException('Rapport inconnu : ' . $key);

        return match ($report['kind']) {
            'period' => $this->periodReport($report['label'], $report['arg'], $from, $to),
            'dimension' => $this->dimensionReport($report['label'], $report['arg'], $from, $to),
            'feeType' => $this->aggregateReport($report['label'], 'Type de frais', $this->paymentDetails->reportByFeeType($from, $to)),
            'discounts' => $this->discountReport($report['label'], $from, $to),
            'cancellations' => $this->cancellationReport($report['label'], $from, $to),
            'outstanding' => $this->outstandingReport($report['label']),
            'collection' => $this->collectionReport($report['label']),
        };
    }

    private function periodReport(string $label, string $granularity, \DateTimeImmutable $from, \DateTimeImmutable $to): ReportResult
    {
        $rows = [];
        $count = 0;
        $total = 0.0;

        foreach ($this->payments->reportByPeriod($granularity, $from, $to) as $bucket) {
            $rows[] = [$bucket['bucket'], $bucket['count'], $bucket['total']];
            $count += $bucket['count'];
            $total += $bucket['total'];
        }

        return new ReportResult($label, ['Periode', 'Operations', 'Montant'], $rows, ['Total', $count, $total], [2]);
    }

    private function dimensionReport(string $label, string $dimension, \DateTimeImmutable $from, \DateTimeImmutable $to): ReportResult
    {
        return $this->aggregateReport($label, 'Libelle', $this->payments->reportByDimension($dimension, $from, $to));
    }

    /**
     * @param array<int, array{label: string, count: int, total: float}> $data
     */
    private function aggregateReport(string $label, string $firstHeader, array $data): ReportResult
    {
        $rows = [];
        $count = 0;
        $total = 0.0;

        foreach ($data as $entry) {
            $rows[] = [$entry['label'], $entry['count'], $entry['total']];
            $count += $entry['count'];
            $total += $entry['total'];
        }

        return new ReportResult($label, [$firstHeader, 'Operations', 'Montant'], $rows, ['Total', $count, $total], [2]);
    }

    private function discountReport(string $label, \DateTimeImmutable $from, \DateTimeImmutable $to): ReportResult
    {
        $rows = [];

        foreach ($this->studentDiscounts->grantedBetween($from, $to) as $studentDiscount) {
            $definition = $studentDiscount->getDiscount();
            $value = $definition?->getType() === Discount::TYPE_PERCENT
                ? $definition->getValue() . ' %'
                : number_format((float) $definition?->getValue(), 0, ',', ' ') . ' Ar';

            $rows[] = [
                $studentDiscount->getCreatedAt()->format('d/m/Y'),
                $studentDiscount->getStudent()?->getFullName() ?? '',
                $definition?->getName() ?? '',
                $value,
                $studentDiscount->getApprovedBy()?->getFullName() ?? 'Non renseigne',
                $studentDiscount->getJustification() ?? '',
            ];
        }

        return new ReportResult($label, ['Date', 'Eleve', 'Reduction', 'Valeur', 'Approuve par', 'Justification'], $rows);
    }

    private function cancellationReport(string $label, \DateTimeImmutable $from, \DateTimeImmutable $to): ReportResult
    {
        $rows = [];
        $total = 0.0;

        foreach ($this->payments->cancelledBetween($from, $to) as $payment) {
            $rows[] = [
                $payment->getCancelledAt()?->format('d/m/Y H:i') ?? '',
                $payment->getPaymentNumber(),
                $payment->getStudent()?->getFullName() ?? '',
                (float) $payment->getTotalAmount(),
                $payment->getCancelledBy()?->getFullName() ?? 'Non renseigne',
                $payment->getCancellationReason() ?? '',
            ];
            $total += (float) $payment->getTotalAmount();
        }

        return new ReportResult(
            $label,
            ['Annule le', 'Numero', 'Eleve', 'Montant', 'Annule par', 'Motif'],
            $rows,
            ['Total', '', '', $total, '', ''],
            [3]
        );
    }

    /**
     * Parcourt tous les eleves : c'est un rapport, declenche a la demande et
     * destine a l'export, contrairement a l'ecran des impayes qui pagine.
     */
    private function outstandingReport(string $label): ReportResult
    {
        $rows = [];
        $total = 0.0;

        foreach ($this->students->findAll() as $student) {
            $situation = $this->calculator->getStudentSituation($student);
            if ($situation['remainingTotal'] <= 0) {
                continue;
            }

            $parent = $student->getParents()->first() ?: null;
            $rows[] = [
                $student->getRegistrationNumber(),
                $student->getFullName(),
                (string) $student->getSchoolClass(),
                $parent?->getFullName() ?? '',
                $parent?->getPhone() ?? '',
                $situation['netTotal'],
                $situation['paidTotal'],
                $situation['remainingTotal'],
            ];
            $total += $situation['remainingTotal'];
        }

        usort($rows, static fn (array $a, array $b): int => $b[7] <=> $a[7]);

        return new ReportResult(
            $label,
            ['Matricule', 'Eleve', 'Classe', 'Parent', 'Telephone', 'Attendu', 'Paye', 'Reste'],
            $rows,
            ['Total', '', '', '', '', '', '', $total],
            [5, 6, 7]
        );
    }

    private function collectionReport(string $label): ReportResult
    {
        $overview = $this->overview();

        $rows = [
            ['Eleves', $overview['students'], ''],
            ['Eleves en impaye', $overview['unpaidStudents'], ''],
            ['Montant attendu', '', $overview['expected']],
            ['Montant encaisse', '', $overview['paid']],
            ['Reste a recouvrer', '', $overview['remaining']],
            ['Taux de recouvrement', $overview['collectionRate'] . ' %', ''],
        ];

        return new ReportResult($label, ['Indicateur', 'Valeur', 'Montant'], $rows, [], [2]);
    }
}
