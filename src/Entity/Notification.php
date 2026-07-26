<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Index(columns: ['reference'], name: 'idx_notification_reference')]
class Notification
{
    public const TYPE_NEW_PAYMENT = 'nouveau_paiement';
    public const TYPE_PAYMENT_CANCELLED = 'paiement_annule';
    public const TYPE_RECEIPT_AVAILABLE = 'recu_disponible';
    public const TYPE_DEADLINE_NEAR = 'echeance_proche';
    public const TYPE_DEADLINE_PASSED = 'echeance_depassee';
    public const TYPE_DISCOUNT_GRANTED = 'reduction_accordee';
    public const TYPE_PARENT_ACCOUNT = 'compte_parent';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 160)]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    private string $message = '';

    #[ORM\Column(length: 40)]
    private string $type = 'info';

    #[ORM\Column]
    private bool $isRead = false;

    /**
     * Cle metier de l'evenement notifie, par exemple "fee:12:echeance_proche".
     * Elle permet de ne pas renvoyer deux fois la meme alerte : sans elle, la
     * commande d'echeances repeterait ses avis a chaque execution.
     */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->sentAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): self { $this->message = $message; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $isRead): self { $this->isRead = $isRead; return $this; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $reference): self { $this->reference = $reference; return $this; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function setSentAt(?\DateTimeImmutable $sentAt): self { $this->sentAt = $sentAt; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
