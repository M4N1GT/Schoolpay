<?php

namespace App\Entity;

use App\Repository\FeeAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FeeAssignmentRepository::class)]
#[ORM\Index(columns: ['due_date'], name: 'idx_fee_due_date')]
class FeeAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FeeType::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?FeeType $feeType = null;

    #[ORM\ManyToOne(targetEntity: SchoolYear::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolYear $schoolYear = null;

    #[ORM\ManyToOne(targetEntity: SchoolClass::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SchoolClass $schoolClass = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Student $student = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    #[Assert\Positive(message: 'Le montant doit etre positif.')]
    private string $amount = '0.00';

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $monthNumber = null;

    #[ORM\Column]
    private bool $isMandatory = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->dueDate = new \DateTimeImmutable('+30 days');
    }

    public function __toString(): string { return ($this->feeType ? $this->feeType->getName() : 'Frais') . ' - ' . number_format((float) $this->amount, 0, ',', ' ') . ' Ar'; }
    public function getId(): ?int { return $this->id; }
    public function getFeeType(): ?FeeType { return $this->feeType; }
    public function setFeeType(?FeeType $feeType): self { $this->feeType = $feeType; return $this; }
    public function getSchoolYear(): ?SchoolYear { return $this->schoolYear; }
    public function setSchoolYear(?SchoolYear $schoolYear): self { $this->schoolYear = $schoolYear; return $this; }
    public function getSchoolClass(): ?SchoolClass { return $this->schoolClass; }
    public function setSchoolClass(?SchoolClass $schoolClass): self { $this->schoolClass = $schoolClass; return $this; }
    public function getStudent(): ?Student { return $this->student; }
    public function setStudent(?Student $student): self { $this->student = $student; return $this; }
    public function getAmount(): string { return $this->amount; }
    public function setAmount(string|float $amount): self { $this->amount = number_format((float) $amount, 2, '.', ''); return $this; }
    public function getDueDate(): ?\DateTimeImmutable { return $this->dueDate; }
    public function setDueDate(?\DateTimeImmutable $dueDate): self { $this->dueDate = $dueDate; return $this; }
    public function getStartDate(): ?\DateTimeImmutable { return $this->startDate; }
    public function setStartDate(?\DateTimeImmutable $startDate): self { $this->startDate = $startDate; return $this; }
    public function getEndDate(): ?\DateTimeImmutable { return $this->endDate; }
    public function setEndDate(?\DateTimeImmutable $endDate): self { $this->endDate = $endDate; return $this; }
    public function getMonthNumber(): ?int { return $this->monthNumber; }
    public function setMonthNumber(?int $monthNumber): self { $this->monthNumber = $monthNumber; return $this; }
    public function isMandatory(): bool { return $this->isMandatory; }
    public function setIsMandatory(bool $isMandatory): self { $this->isMandatory = $isMandatory; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
