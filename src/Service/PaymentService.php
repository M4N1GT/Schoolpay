<?php

namespace App\Service;

use App\Entity\FeeAssignment;
use App\Entity\Payment;
use App\Entity\PaymentDetail;
use App\Entity\Student;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentNumberGenerator $paymentNumberGenerator,
        private PaymentCalculationService $calculationService,
        private ReceiptService $receiptService,
        private AuditLoggerService $auditLogger,
    ) {
    }

    /**
     * @param FeeAssignment[] $feeAssignments
     */
    public function registerPayment(Student $student, array $feeAssignments, float $amount, string $method, ?string $reference, ?string $notes, ?User $receivedBy): Payment
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant du paiement doit etre strictement positif.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($student, $feeAssignments, $amount, $method, $reference, $notes, $receivedBy): Payment {
            $payment = new Payment();
            $payment->setPaymentNumber($this->paymentNumberGenerator->generate());
            $payment->setStudent($student);
            $payment->setSchoolYear($student->getSchoolYear());
            $payment->setTotalAmount($amount);
            $payment->setPaymentMethod($method);
            $payment->setExternalReference($reference);
            $payment->setNotes($notes);
            $payment->setReceivedBy($receivedBy);

            $remainingCash = $amount;
            foreach ($feeAssignments as $feeAssignment) {
                if ($remainingCash <= 0) {
                    break;
                }
                $remainingFee = $this->calculationService->getRemainingAmount($student, $feeAssignment);
                $allocated = min($remainingCash, $remainingFee);

                if ($allocated <= 0) {
                    continue;
                }

                $detail = new PaymentDetail();
                $detail->setFeeAssignment($feeAssignment);
                $detail->setAmount($allocated);
                $payment->addDetail($detail);
                $remainingCash -= $allocated;
            }

            if ($payment->getDetails()->isEmpty()) {
                throw new \InvalidArgumentException('Aucun frais selectionne ne peut recevoir ce paiement.');
            }

            // Un paiement doit toujours egaler la somme de ses affectations : on
            // refuse tout excedent plutot que de le perdre silencieusement.
            if ($remainingCash > 0.01) {
                throw new \InvalidArgumentException(sprintf(
                    'Le montant depasse de %s Ar le reste du sur les frais selectionnes. Ajustez le montant ou selectionnez d autres frais.',
                    number_format($remainingCash, 2, ',', ' ')
                ));
            }

            $receipt = $this->receiptService->createForPayment($payment);
            $this->entityManager->persist($payment);
            $this->entityManager->persist($receipt);
            $this->auditLogger->log('paiement', Payment::class, null, 'Paiement enregistre pour ' . $student->getFullName(), $receivedBy);

            return $payment;
        });
    }

    public function cancelPayment(Payment $payment, string $reason, ?User $user): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Le motif d annulation est obligatoire.');
        }

        $payment->setStatus(Payment::STATUS_CANCELLED);
        $payment->setCancellationReason($reason);
        $payment->setCancelledAt(new \DateTimeImmutable());
        $payment->setCancelledBy($user);
        $payment->touch();
        $this->auditLogger->log('annulation_paiement', Payment::class, $payment->getId(), $reason, $user);
        $this->entityManager->flush();
    }
}
