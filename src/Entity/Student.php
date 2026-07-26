<?php

namespace App\Entity;

use App\Repository\StudentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StudentRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_student_registration', columns: ['registration_number'])]
#[ORM\Index(columns: ['last_name', 'first_name'], name: 'idx_student_name')]
#[ORM\Index(columns: ['status'], name: 'idx_student_status')]
#[UniqueEntity(fields: ['registrationNumber'], message: 'Ce matricule est deja utilise.')]
class Student
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private string $registrationNumber = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $firstName = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $lastName = '';

    #[ORM\Column(length: 20)]
    private string $gender = 'Non precise';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $birthPlace = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(length: 40)]
    private string $status = 'actif';

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $enrollmentDate = null;

    #[ORM\ManyToOne(targetEntity: SchoolClass::class, inversedBy: 'students')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolClass $schoolClass = null;

    #[ORM\ManyToOne(targetEntity: SchoolYear::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolYear $schoolYear = null;

    #[ORM\ManyToMany(targetEntity: ParentGuardian::class, inversedBy: 'students')]
    #[ORM\JoinTable(name: 'student_parent_guardian')]
    private Collection $parents;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->parents = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->enrollmentDate = new \DateTimeImmutable();
    }

    public function __toString(): string { return $this->registrationNumber . ' - ' . $this->getFullName(); }
    public function getId(): ?int { return $this->id; }
    public function getRegistrationNumber(): string { return $this->registrationNumber; }
    public function setRegistrationNumber(string $registrationNumber): self { $this->registrationNumber = strtoupper(trim($registrationNumber)); return $this; }
    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }
    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $lastName): self { $this->lastName = $lastName; return $this; }
    public function getFullName(): string { return trim($this->firstName . ' ' . $this->lastName); }
    public function getGender(): string { return $this->gender; }
    public function setGender(string $gender): self { $this->gender = $gender; return $this; }
    public function getBirthDate(): ?\DateTimeImmutable { return $this->birthDate; }
    public function setBirthDate(?\DateTimeImmutable $birthDate): self { $this->birthDate = $birthDate; return $this; }
    public function getBirthPlace(): ?string { return $this->birthPlace; }
    public function setBirthPlace(?string $birthPlace): self { $this->birthPlace = $birthPlace; return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }
    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $photo): self { $this->photo = $photo; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getEnrollmentDate(): ?\DateTimeImmutable { return $this->enrollmentDate; }
    public function setEnrollmentDate(?\DateTimeImmutable $enrollmentDate): self { $this->enrollmentDate = $enrollmentDate; return $this; }
    public function getSchoolClass(): ?SchoolClass { return $this->schoolClass; }
    public function setSchoolClass(?SchoolClass $schoolClass): self { $this->schoolClass = $schoolClass; return $this; }
    public function getSchoolYear(): ?SchoolYear { return $this->schoolYear; }
    public function setSchoolYear(?SchoolYear $schoolYear): self { $this->schoolYear = $schoolYear; return $this; }
    public function getParents(): Collection { return $this->parents; }
    public function addParent(ParentGuardian $parent): self { if (!$this->parents->contains($parent)) { $this->parents->add($parent); } return $this; }
    public function removeParent(ParentGuardian $parent): self { $this->parents->removeElement($parent); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
