<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function create(User $user, string $title, string $message, string $type = 'info'): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setType($type);
        $this->entityManager->persist($notification);

        return $notification;
    }
}
