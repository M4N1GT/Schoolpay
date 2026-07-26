<?php

namespace App\Entity;

use App\Repository\ReceiptRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReceiptRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_receipt_number', columns: ['receipt_number'])]
#[ORM\UniqueConstraint(name: 'uniq_receipt_verification', columns: ['verification_code'])]
class Receipt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    private string $receiptNumber = '';

    #[ORM\OneToOne(inversedBy: 'receipt', targetEntity: Payment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Payment $payment = null;

    #[ORM\Column]
    private \DateTimeImmutable $generatedAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(length: 80)]
    private string $verificationCode = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $qrCodeData = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->generatedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getReceiptNumber(): string { return $this->receiptNumber; }
    public function setReceiptNumber(string $receiptNumber): self { $this->receiptNumber = $receiptNumber; return $this; }
    public function getPayment(): ?Payment { return $this->payment; }
    public function setPayment(?Payment $payment): self { $this->payment = $payment; return $this; }
    public function getGeneratedAt(): \DateTimeImmutable { return $this->generatedAt; }
    public function setGeneratedAt(\DateTimeImmutable $generatedAt): self { $this->generatedAt = $generatedAt; return $this; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(?string $filePath): self { $this->filePath = $filePath; return $this; }
    public function getVerificationCode(): string { return $this->verificationCode; }
    public function setVerificationCode(string $verificationCode): self { $this->verificationCode = $verificationCode; return $this; }
    public function getQrCodeData(): ?string { return $this->qrCodeData; }
    public function setQrCodeData(?string $qrCodeData): self { $this->qrCodeData = $qrCodeData; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
