<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Student;
use App\Entity\User;
use App\Notification\NotificationChannelInterface;
use App\Repository\NotificationRepository;

class NotificationService
{
    /**
     * @param iterable<NotificationChannelInterface> $channels
     */
    public function __construct(
        private NotificationRepository $notifications,
        private iterable $channels,
    ) {
    }

    /**
     * @param string|null $reference cle metier permettant d'eviter les doublons
     */
    public function notify(User $user, string $type, string $title, string $message, ?string $reference = null): ?Notification
    {
        if ($reference !== null && $this->notifications->existsFor($user, $reference)) {
            return null;
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setReference($reference);

        foreach ($this->channels as $channel) {
            if ($channel->supports($notification)) {
                $channel->send($notification);
            }
        }

        return $notification;
    }

    /**
     * Previent tous les responsables legaux d'un eleve disposant d'un compte.
     *
     * Un parent sans compte utilisateur n'est simplement pas notifie : c'est le
     * cas d'une famille suivie par l'ecole mais sans acces en ligne.
     *
     * @return Notification[]
     */
    public function notifyGuardiansOf(Student $student, string $type, string $title, string $message, ?string $reference = null): array
    {
        $sent = [];

        foreach ($student->getParents() as $guardian) {
            $user = $guardian->getUser();
            if (!$user instanceof User) {
                continue;
            }

            $notification = $this->notify($user, $type, $title, $message, $reference);
            if ($notification instanceof Notification) {
                $sent[] = $notification;
            }
        }

        return $sent;
    }
}
