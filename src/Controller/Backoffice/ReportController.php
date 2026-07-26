<?php

namespace App\Controller\Backoffice;

use App\Repository\PaymentRepository;
use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReportController extends AbstractController
{
    #[Route('/backoffice/reports', name: 'backoffice_report_index')]
    public function index(ReportService $reports, PaymentRepository $payments): Response
    {
        return $this->render('backoffice/report/index.html.twig', [
            'overview' => $reports->overview(),
            'payments' => $payments->findBy([], ['paymentDate' => 'DESC']),
        ]);
    }
}
