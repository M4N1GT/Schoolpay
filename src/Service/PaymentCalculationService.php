<?php

namespace App\Service;

use App\Entity\Discount;
use App\Entity\FeeAssignment;
use App\Entity\Student;
use App\Repository\FeeAssignmentRepository;
use App\Repository\PaymentDetailRepository;
use App\Repository\StudentDiscountRepository;

class PaymentCalculationService
{
    public function __construct(
        private FeeAssignmentRepository $feeAssignments,
        private StudentDiscountRepository $studentDiscounts,
        private PaymentDetailRepository $paymentDetails,
    ) {
    }

    public function getStudentSituation(Student $student): array
    {
        $items = [];
        $grossTotal = 0.0;
        $discountTotal = 0.0;
        $paidTotal = 0.0;

        foreach ($this->feeAssignments->findForStudent($student) as $feeAssignment) {
            $gross = (float) $feeAssignment->getAmount();
            $discount = $this->calculateDiscount($student, $feeAssignment, $gross);
            $net = max(0.0, $gross - $discount);
            $paid = $this->getPaidAmount($feeAssignment,$student);
            $remaining = max(0.0, $net - $paid);

            $items[] = [
                'fee' => $feeAssignment,
                'gross' => $gross,
                'discount' => $discount,
                'net' => $net,
                'paid' => $paid,
                'remaining' => $remaining,
                'status' => $this->getFeeStatus($feeAssignment, $net, $paid),
            ];

            $grossTotal += $gross;
            $discountTotal += $discount;
            $paidTotal += $paid;
        }

        $netTotal = max(0.0, $grossTotal - $discountTotal);

        return [
            'items' => $items,
            'grossTotal' => $grossTotal,
            'discountTotal' => $discountTotal,
            'netTotal' => $netTotal,
            'paidTotal' => $paidTotal,
            'remainingTotal' => max(0.0, $netTotal - $paidTotal),
            'overpaid' => max(0.0, $paidTotal - $netTotal),
        ];
    }
    
    public function getPaidAmount(FeeAssignment $feeAssignment, Student $student): float
    {
        return $this->paymentDetails->sumPaidForStudent($feeAssignment, $student);
    }
        /** Montant reellement du par cet eleve pour ce frais, reductions deduites. */
    public function getNetAmount(Student $student, FeeAssignment $feeAssignment): float
    {
        $gross = (float) $feeAssignment->getAmount();

        return max(0.0, $gross - $this->calculateDiscount($student, $feeAssignment, $gross));
    }

    /** Reste a payer par cet eleve sur ce frais. */
    public function getRemainingAmount(Student $student, FeeAssignment $feeAssignment): float
    {
        return max(0.0, $this->getNetAmount($student, $feeAssignment) - $this->getPaidAmount($feeAssignment, $student));
    }


   

    private function calculateDiscount(Student $student, FeeAssignment $feeAssignment, float $gross): float
    {
        $discount = 0.0;
        $today = new \DateTimeImmutable('today');

        foreach ($this->studentDiscounts->findBy(['student' => $student]) as $studentDiscount) {
            // Reduction ciblant un frais precis : elle ne vaut que pour lui.
            if ($studentDiscount->getFeeAssignment() && $studentDiscount->getFeeAssignment() !== $feeAssignment) {
                continue;
            }

            // Une reduction accordee au titre d'une annee scolaire ne doit pas
            // se reporter sur les frais d'une autre annee.
            $grantedFor = $studentDiscount->getSchoolYear();
            if ($grantedFor && $feeAssignment->getSchoolYear() && $grantedFor !== $feeAssignment->getSchoolYear()) {
                continue;
            }

            $definition = $studentDiscount->getDiscount();
            if (!$definition?->isActive() || !$this->isInEffect($definition, $today)) {
                continue;
            }

            $discount += $definition->getType() === Discount::TYPE_PERCENT
                ? $gross * ((float) $definition->getValue() / 100)
                : (float) $definition->getValue();
        }

        // Plafonnement : une reduction ne rend jamais le montant negatif.
        return min($gross, $discount);
    }

    /**
     * Une reduction bornee dans le temps cesse de s'appliquer hors de sa
     * periode : sans ce controle, une remise exceptionnelle expiree
     * continuerait indefiniment a diminuer les montants dus.
     */
    private function isInEffect(Discount $discount, \DateTimeImmutable $today): bool
    {
        if ($discount->getStartDate() && $discount->getStartDate() > $today) {
            return false;
        }

        return !($discount->getEndDate() && $discount->getEndDate() < $today);
    }

    private function getFeeStatus(FeeAssignment $feeAssignment, float $net, float $paid): string
    {
        if ($net <= 0.0) {
            return 'exonere';
        }

        if ($paid >= $net) {
            return 'paye';
        }

        if ($paid > 0.0) {
            return 'partiellement_paye';
        }

        if ($feeAssignment->getDueDate() && $feeAssignment->getDueDate() < new \DateTimeImmutable('today')) {
            return 'en_retard';
        }

        return 'non_paye';
    }
}
