<?php

namespace App\Entity;

use App\Repository\ParentGuardianRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParentGuardianRepository::class)]
#[ORM\Index(columns: ['email'], name: 'idx_parent_email')]
class ParentGuardian
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $firstName = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $lastName = '';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private string $phone = '';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $alternatePhone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $profession = null;

    #[ORM\Column(length: 60)]
    private string $relationshipType = 'Parent';

    #[ORM\OneToOne(inversedBy: 'parentProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToMany(targetEntity: Student::class, mappedBy: 'parents')]
    private Collection $students;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->students = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string { return $this->getFullName(); }
    public function getId(): ?int { return $this->id; }
    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }
    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $lastName): self { $this->lastName = $lastName; return $this; }
    public function getFullName(): string { return trim($this->firstName . ' ' . $this->lastName); }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email ? strtolower(trim($email)) : null; return $this; }
    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $phone): self { $this->phone = $phone; return $this; }
    public function getAlternatePhone(): ?string { return $this->alternatePhone; }
    public function setAlternatePhone(?string $alternatePhone): self { $this->alternatePhone = $alternatePhone; return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }
    public function getProfession(): ?string { return $this->profession; }
    public function setProfession(?string $profession): self { $this->profession = $profession; return $this; }
    public function getRelationshipType(): string { return $this->relationshipType; }
    public function setRelationshipType(string $relationshipType): self { $this->relationshipType = $relationshipType; return $this; }
    public function getUser(): ?User { return $this->user; }
    /**
     * Synchronise le cote inverse : User::parentProfile est le cote non
     * proprietaire de la relation, Doctrine ne le renseigne qu'au chargement.
     * Sans cette mise a jour, un compte parent rattache pendant la requete
     * courante voit son profil rester nul, et l'espace parent lui est refuse.
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;
        $user?->setParentProfile($this);

        return $this;
    }
    public function getStudents(): Collection { return $this->students; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
