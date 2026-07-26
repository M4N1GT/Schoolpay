<?php

namespace App\Entity;

use App\Repository\SchoolSettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchoolSettingRepository::class)]
class SchoolSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $schoolName = 'SchoolPay Academy';

    #[ORM\Column(length: 40)]
    private string $schoolCode = 'SCHOOLPAY';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(length: 40)]
    private string $currency = 'Ariary';

    #[ORM\Column(length: 12)]
    private string $currencySymbol = 'Ar';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $receiptFooter = null;

    #[ORM\Column]
    private int $defaultPaymentDeadline = 30;

    #[ORM\Column]
    private bool $lateFeeEnabled = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSchoolName(): string { return $this->schoolName; }
    public function setSchoolName(string $schoolName): self { $this->schoolName = $schoolName; return $this; }
    public function getSchoolCode(): string { return $this->schoolCode; }
    public function setSchoolCode(string $schoolCode): self { $this->schoolCode = $schoolCode; return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email; return $this; }
    public function getLogo(): ?string { return $this->logo; }
    public function setLogo(?string $logo): self { $this->logo = $logo; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): self { $this->currency = $currency; return $this; }
    public function getCurrencySymbol(): string { return $this->currencySymbol; }
    public function setCurrencySymbol(string $currencySymbol): self { $this->currencySymbol = $currencySymbol; return $this; }
    public function getReceiptFooter(): ?string { return $this->receiptFooter; }
    public function setReceiptFooter(?string $receiptFooter): self { $this->receiptFooter = $receiptFooter; return $this; }
    public function getDefaultPaymentDeadline(): int { return $this->defaultPaymentDeadline; }
    public function setDefaultPaymentDeadline(int $defaultPaymentDeadline): self { $this->defaultPaymentDeadline = $defaultPaymentDeadline; return $this; }
    public function isLateFeeEnabled(): bool { return $this->lateFeeEnabled; }
    public function setLateFeeEnabled(bool $lateFeeEnabled): self { $this->lateFeeEnabled = $lateFeeEnabled; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
