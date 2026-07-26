<?php

namespace App\Controller\Backoffice;

use App\Repository\AuditLogRepository;
use App\Repository\ParentGuardianRepository;
use App\Repository\PaymentRepository;
use App\Repository\ReceiptRepository;
use App\Repository\SchoolClassRepository;
use App\Repository\StudentRepository;
use App\Repository\UserRepository;
use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Chaque role dispose de son propre tableau de bord (cahier des charges,
 * section 18). L'aiguillage est ordonne du role le plus large au plus etroit :
 * un compte cumulant plusieurs roles voit le tableau de bord le plus complet
 * auquel il a droit.
 */
class DashboardController extends AbstractController
{
    #[Route('/backoffice', name: 'backoffice_dashboard')]
    public function index(
        ReportService $reports,
        StudentRepository $students,
        SchoolClassRepository $classes,
        ParentGuardianRepository $parents,
        UserRepository $users,
        PaymentRepository $payments,
        ReceiptRepository $receipts,
        AuditLogRepository $audits,
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->adminDashboard($reports, $students, $classes, $parents, $users, $payments, $receipts, $audits);
        }

        if ($this->isGranted('ROLE_COMPTABLE')) {
            return $this->accountantDashboard($payments, $receipts);
        }

        return $this->directorDashboard($reports, $payments);
    }

    private function adminDashboard(
        ReportService $reports,
        StudentRepository $students,
        SchoolClassRepository $classes,
        ParentGuardianRepository $parents,
        UserRepository $users,
        PaymentRepository $payments,
        ReceiptRepository $receipts,
        AuditLogRepository $audits,
    ): Response {
        return $this->render('backoffice/dashboard/admin.html.twig', [
            'overview' => $reports->overview(),
            'stats' => [
                'students' => $students->count([]),
                'parents' => $parents->count([]),
                'classes' => $classes->count([]),
                'users' => $users->count([]),
                'receipts' => $receipts->count([]),
            ],
            'today' => $payments->summaryBetween(...$this->todayRange()),
            'month' => $payments->summaryBetween(...$this->monthRange()),
            'recentPayments' => $payments->findBy([], ['paymentDate' => 'DESC'], 8),
            'recentAudits' => $audits->findBy([], ['createdAt' => 'DESC'], 8),
        ]);
    }

    private function accountantDashboard(PaymentRepository $payments, ReceiptRepository $receipts): Response
    {
        return $this->render('backoffice/dashboard/accountant.html.twig', [
            'today' => $payments->summaryBetween(...$this->todayRange()),
            'month' => $payments->summaryBetween(...$this->monthRange()),
            'byMethod' => $payments->sumByMethod(),
            'recentPayments' => $payments->findBy([], ['paymentDate' => 'DESC'], 10),
            'recentReceipts' => $receipts->findBy([], ['generatedAt' => 'DESC'], 8),
        ]);
    }

    private function directorDashboard(ReportService $reports, PaymentRepository $payments): Response
    {
        return $this->render('backoffice/dashboard/director.html.twig', [
            'overview' => $reports->overview(),
            'today' => $payments->summaryBetween(...$this->todayRange()),
            'byDay' => $payments->revenueSeries('day', 14),
            'byMonth' => $payments->revenueSeries('month', 12),
            'byClass' => $payments->sumByStudentField('name'),
            'byLevel' => $payments->sumByStudentField('level'),
        ]);
    }

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} */
    private function todayRange(): array
    {
        $start = new \DateTimeImmutable('today');

        return [$start, $start->modify('+1 day')];
    }

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} */
    private function monthRange(): array
    {
        $start = new \DateTimeImmutable('first day of this month 00:00:00');

        return [$start, $start->modify('+1 month')];
    }
}
