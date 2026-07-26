<?php

namespace App\Notification;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Canal interne : la notification est conservee en base et consultee depuis
 * l'espace parent. C'est le seul canal reellement operationnel, les autres
 * supposant une API et des identifiants dont le projet ne dispose pas.
 */
class DatabaseChannel implements NotificationChannelInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function supports(Notification $notification): bool
    {
        return $notification->getUser() !== null;
    }

    public function send(Notification $notification): void
    {
        $notification->setSentAt(new \DateTimeImmutable());
        $this->entityManager->persist($notification);
    }
}
