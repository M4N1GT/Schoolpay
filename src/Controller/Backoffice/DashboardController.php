<?php

namespace App\Controller\Backoffice;

use App\Repository\AuditLogRepository;
use App\Repository\PaymentRepository;
use App\Repository\ReceiptRepository;
use App\Repository\SchoolClassRepository;
use App\Repository\StudentRepository;
use App\Repository\UserRepository;
use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/backoffice', name: 'backoffice_dashboard')]
    public function index(
        ReportService $reports,
        StudentRepository $students,
        SchoolClassRepository $classes,
        UserRepository $users,
        PaymentRepository $payments,
        ReceiptRepository $receipts,
        AuditLogRepository $audits,
    ): Response {
        return $this->render('backoffice/dashboard.html.twig', [
            'overview' => $reports->overview(),
            'stats' => [
                'students' => $students->count([]),
                'classes' => $classes->count([]),
                'users' => $users->count([]),
                'payments' => $payments->count([]),
                'receipts' => $receipts->count([]),
            ],
            'recentPayments' => $payments->findBy([], ['paymentDate' => 'DESC'], 8),
            'recentAudits' => $audits->findBy([], ['createdAt' => 'DESC'], 8),
        ]);
    }
}
