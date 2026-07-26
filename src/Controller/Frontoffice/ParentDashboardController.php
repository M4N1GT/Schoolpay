<?php

namespace App\Controller\Frontoffice;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\ReceiptRepository;
use App\Repository\StudentRepository;
use App\Service\PaymentCalculationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/parent')]
class ParentDashboardController extends AbstractController
{
    #[Route('', name: 'parent_dashboard')]
    public function dashboard(PaymentCalculationService $calculator, NotificationRepository $notifications): Response
    {
        $parent = $this->parentProfile();
        $children = $parent?->getStudents() ?? [];
        $situations = [];
        $totals = ['expected' => 0.0, 'paid' => 0.0, 'remaining' => 0.0];
        $upcoming = [];

        foreach ($children as $child) {
            $situation = $calculator->getStudentSituation($child);
            $situations[$child->getId()] = $situation;

            $totals['expected'] += $situation['netTotal'];
            $totals['paid'] += $situation['paidTotal'];
            $totals['remaining'] += $situation['remainingTotal'];

            foreach ($situation['items'] as $item) {
                if ($item['remaining'] > 0 && $item['fee']->getDueDate()) {
                    $upcoming[] = ['student' => $child, 'item' => $item];
                }
            }
        }

        // Echeances les plus proches en tete, tous enfants confondus : c'est
        // ce qu'un parent veut voir en arrivant.
        usort($upcoming, fn (array $a, array $b): int => $a['item']['fee']->getDueDate() <=> $b['item']['fee']->getDueDate());

        $user = $this->getUser();

        return $this->render('frontoffice/parent/dashboard.html.twig', [
            'parent' => $parent,
            'children' => $children,
            'situations' => $situations,
            'totals' => $totals,
            'upcoming' => array_slice($upcoming, 0, 5),
            'notifications' => $user instanceof User ? $notifications->findBy(['user' => $user], ['createdAt' => 'DESC'], 5) : [],
            'unreadCount' => $user instanceof User ? $notifications->count(['user' => $user, 'isRead' => false]) : 0,
        ]);
    }

    #[Route('/child/{id}', name: 'parent_child_show')]
    public function child(int $id, StudentRepository $students, PaymentCalculationService $calculator, ReceiptRepository $receipts): Response
    {
        $student = $students->find($id);
        if (!$student) {
            throw $this->createNotFoundException();
        }

        $parent = $this->parentProfile();
        if (!$parent || !$student->getParents()->contains($parent)) {
            throw $this->createAccessDeniedException('Cet eleve n est pas associe a votre compte parent.');
        }

        return $this->render('frontoffice/parent/child.html.twig', [
            'student' => $student,
            'situation' => $calculator->getStudentSituation($student),
            'receipts' => array_filter($receipts->findBy([], ['generatedAt' => 'DESC']), fn ($receipt) => $receipt->getPayment()->getStudent() === $student),
        ]);
    }

    #[Route('/profile', name: 'parent_profile')]
    public function profile(): Response
    {
        return $this->render('frontoffice/parent/profile.html.twig', ['parent' => $this->parentProfile()]);
    }

    private function parentProfile()
    {
        $user = $this->getUser();
        return $user instanceof User ? $user->getParentProfile() : null;
    }
}
