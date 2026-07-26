<?php

namespace App\Entity;

use App\Repository\PaymentDetailRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentDetailRepository::class)]
class PaymentDetail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Payment::class, inversedBy: 'details')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Payment $payment = null;

    #[ORM\ManyToOne(targetEntity: FeeAssignment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?FeeAssignment $feeAssignment = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    #[Assert\Positive]
    private string $amount = '0.00';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPayment(): ?Payment { return $this->payment; }
    public function setPayment(?Payment $payment): self { $this->payment = $payment; return $this; }
    public function getFeeAssignment(): ?FeeAssignment { return $this->feeAssignment; }
    public function setFeeAssignment(?FeeAssignment $feeAssignment): self { $this->feeAssignment = $feeAssignment; return $this; }
    public function getAmount(): string { return $this->amount; }
    public function setAmount(string|float $amount): self { $this->amount = number_format((float) $amount, 2, '.', ''); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
