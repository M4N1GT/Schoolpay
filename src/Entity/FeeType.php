<?php

namespace App\Entity;

use App\Repository\FeeTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FeeTypeRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_fee_type_code', columns: ['code'])]
#[UniqueEntity(fields: ['code'], message: 'Ce code de frais existe deja.')]
class FeeType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private string $code = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $isMandatory = true;

    #[ORM\Column]
    private bool $isRecurring = false;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $recurrenceType = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string { return $this->name; }
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): self { $this->code = strtoupper(trim($code)); return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function isMandatory(): bool { return $this->isMandatory; }
    public function setIsMandatory(bool $isMandatory): self { $this->isMandatory = $isMandatory; return $this; }
    public function isRecurring(): bool { return $this->isRecurring; }
    public function setIsRecurring(bool $isRecurring): self { $this->isRecurring = $isRecurring; return $this; }
    public function getRecurrenceType(): ?string { return $this->recurrenceType; }
    public function setRecurrenceType(?string $recurrenceType): self { $this->recurrenceType = $recurrenceType; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
