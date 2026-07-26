<?php

namespace App\Entity;

use App\Repository\SchoolClassRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SchoolClassRepository::class)]
#[ORM\Index(columns: ['level'], name: 'idx_class_level')]
class SchoolClass
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 80)]
    private string $level = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $capacity = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: SchoolYear::class, inversedBy: 'classes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolYear $schoolYear = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'schoolClass', targetEntity: Student::class)]
    private Collection $students;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->students = new ArrayCollection();
    }

    public function __toString(): string { return $this->name; }
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getLevel(): string { return $this->level; }
    public function setLevel(string $level): self { $this->level = $level; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getCapacity(): ?int { return $this->capacity; }
    public function setCapacity(?int $capacity): self { $this->capacity = $capacity; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
    public function getSchoolYear(): ?SchoolYear { return $this->schoolYear; }
    public function setSchoolYear(?SchoolYear $schoolYear): self { $this->schoolYear = $schoolYear; return $this; }
    public function getStudents(): Collection { return $this->students; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
