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

        foreach ($children as $child) {
            $situations[$child->getId()] = $calculator->getStudentSituation($child);
        }

        return $this->render('frontoffice/parent/dashboard.html.twig', [
            'parent' => $parent,
            'children' => $children,
            'situations' => $situations,
            'notifications' => $this->getUser() instanceof User ? $notifications->findBy(['user' => $this->getUser()], ['createdAt' => 'DESC'], 5) : [],
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
