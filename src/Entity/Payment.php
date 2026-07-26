<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_payment_number', columns: ['payment_number'])]
#[ORM\Index(columns: ['payment_date'], name: 'idx_payment_date')]
#[ORM\Index(columns: ['status'], name: 'idx_payment_status')]
class Payment
{
    public const STATUS_VALIDATED = 'valide';
    public const STATUS_PARTIAL = 'partiellement_affecte';
    public const STATUS_CANCELLED = 'annule';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    private string $paymentNumber = '';

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: SchoolYear::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolYear $schoolYear = null;

    #[ORM\Column]
    private \DateTimeImmutable $paymentDate;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    #[Assert\Positive(message: 'Le montant du paiement doit etre strictement positif.')]
    private string $totalAmount = '0.00';

    #[ORM\Column(length: 40)]
    private string $paymentMethod = 'especes';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $externalReference = null;

    #[ORM\Column(length: 40)]
    private string $status = self::STATUS_VALIDATED;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'receivedPayments')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $receivedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $cancelledBy = null;

    #[ORM\OneToMany(mappedBy: 'payment', targetEntity: PaymentDetail::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $details;

    #[ORM\OneToOne(mappedBy: 'payment', targetEntity: Receipt::class, cascade: ['persist'])]
    private ?Receipt $receipt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->paymentDate = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->details = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getPaymentNumber(): string { return $this->paymentNumber; }
    public function setPaymentNumber(string $paymentNumber): self { $this->paymentNumber = $paymentNumber; return $this; }
    public function getStudent(): ?Student { return $this->student; }
    public function setStudent(?Student $student): self { $this->student = $student; return $this; }
    public function getSchoolYear(): ?SchoolYear { return $this->schoolYear; }
    public function setSchoolYear(?SchoolYear $schoolYear): self { $this->schoolYear = $schoolYear; return $this; }
    public function getPaymentDate(): \DateTimeImmutable { return $this->paymentDate; }
    public function setPaymentDate(\DateTimeImmutable $paymentDate): self { $this->paymentDate = $paymentDate; return $this; }
    public function getTotalAmount(): string { return $this->totalAmount; }
    public function setTotalAmount(string|float $totalAmount): self { $this->totalAmount = number_format((float) $totalAmount, 2, '.', ''); return $this; }
    public function getPaymentMethod(): string { return $this->paymentMethod; }
    public function setPaymentMethod(string $paymentMethod): self { $this->paymentMethod = $paymentMethod; return $this; }
    public function getExternalReference(): ?string { return $this->externalReference; }
    public function setExternalReference(?string $externalReference): self { $this->externalReference = $externalReference; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
    public function getReceivedBy(): ?User { return $this->receivedBy; }
    public function setReceivedBy(?User $receivedBy): self { $this->receivedBy = $receivedBy; return $this; }
    public function getCancellationReason(): ?string { return $this->cancellationReason; }
    public function setCancellationReason(?string $cancellationReason): self { $this->cancellationReason = $cancellationReason; return $this; }
    public function getCancelledAt(): ?\DateTimeImmutable { return $this->cancelledAt; }
    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): self { $this->cancelledAt = $cancelledAt; return $this; }
    public function getCancelledBy(): ?User { return $this->cancelledBy; }
    public function setCancelledBy(?User $cancelledBy): self { $this->cancelledBy = $cancelledBy; return $this; }
    public function getDetails(): Collection { return $this->details; }
    public function addDetail(PaymentDetail $detail): self { if (!$this->details->contains($detail)) { $this->details->add($detail); $detail->setPayment($this); } return $this; }
    public function getReceipt(): ?Receipt { return $this->receipt; }
    public function setReceipt(?Receipt $receipt): self { $this->receipt = $receipt; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
