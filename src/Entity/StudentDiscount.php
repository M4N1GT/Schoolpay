<?php

namespace App\Entity;

use App\Repository\StudentDiscountRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StudentDiscountRepository::class)]
class StudentDiscount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Discount::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Discount $discount = null;

    #[ORM\ManyToOne(targetEntity: FeeAssignment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?FeeAssignment $feeAssignment = null;

    #[ORM\ManyToOne(targetEntity: SchoolYear::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolYear $schoolYear = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $approvedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $justification = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getStudent(): ?Student { return $this->student; }
    public function setStudent(?Student $student): self { $this->student = $student; return $this; }
    public function getDiscount(): ?Discount { return $this->discount; }
    public function setDiscount(?Discount $discount): self { $this->discount = $discount; return $this; }
    public function getFeeAssignment(): ?FeeAssignment { return $this->feeAssignment; }
    public function setFeeAssignment(?FeeAssignment $feeAssignment): self { $this->feeAssignment = $feeAssignment; return $this; }
    public function getSchoolYear(): ?SchoolYear { return $this->schoolYear; }
    public function setSchoolYear(?SchoolYear $schoolYear): self { $this->schoolYear = $schoolYear; return $this; }
    public function getApprovedBy(): ?User { return $this->approvedBy; }
    public function setApprovedBy(?User $approvedBy): self { $this->approvedBy = $approvedBy; return $this; }
    public function getJustification(): ?string { return $this->justification; }
    public function setJustification(?string $justification): self { $this->justification = $justification; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
