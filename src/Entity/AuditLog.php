<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Index(columns: ['action'], name: 'idx_audit_action')]
#[ORM\Index(columns: ['entity_type'], name: 'idx_audit_entity_type')]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_created_at')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 80)]
    private string $action = '';

    #[ORM\Column(length: 120)]
    private string $entityType = '';

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(nullable: true)]
    private ?array $oldValues = null;

    #[ORM\Column(nullable: true)]
    private ?array $newValues = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): self { $this->action = $action; return $this; }
    public function getEntityType(): string { return $this->entityType; }
    public function setEntityType(string $entityType): self { $this->entityType = $entityType; return $this; }
    public function getEntityId(): ?int { return $this->entityId; }
    public function setEntityId(?int $entityId): self { $this->entityId = $entityId; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }
    public function getOldValues(): ?array { return $this->oldValues; }
    public function setOldValues(?array $oldValues): self { $this->oldValues = $oldValues; return $this; }
    public function getNewValues(): ?array { return $this->newValues; }
    public function setNewValues(?array $newValues): self { $this->newValues = $newValues; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ipAddress): self { $this->ipAddress = $ipAddress; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $userAgent): self { $this->userAgent = $userAgent; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
