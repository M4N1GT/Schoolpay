<?php

namespace App\Controller\Frontoffice;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/parent/notifications')]
class ParentNotificationController extends AbstractController
{
    #[Route('', name: 'parent_notifications')]
    public function index(NotificationRepository $notifications): Response
    {
        $user = $this->getUser();

        return $this->render('frontoffice/parent/notifications.html.twig', [
            'notifications' => $user instanceof User ? $notifications->findBy(['user' => $user], ['createdAt' => 'DESC']) : [],
        ]);
    }

    #[Route('/{id}/read', name: 'parent_notification_read')]
    public function read(int $id, NotificationRepository $notifications, EntityManagerInterface $entityManager): Response
    {
        $notification = $notifications->find($id);
        if (!$notification || $notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $notification->setIsRead(true);
        $entityManager->flush();

        return $this->redirectToRoute('parent_notifications');
    }
}
