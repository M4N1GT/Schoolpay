<?php

namespace App\Controller\Backoffice;

use App\Repository\PaymentRepository;
use App\Service\PaginationService;
use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReportController extends AbstractController
{
    #[Route('/backoffice/reports', name: 'backoffice_report_index')]
    public function index(Request $request, ReportService $reports, PaymentRepository $payments, PaginationService $paginator): Response
    {
        $pagination = $paginator->paginate(
            $payments->createQueryBuilder('p')
                ->leftJoin('p.student', 's')->addSelect('s')
                ->orderBy('p.paymentDate', 'DESC'),
            $paginator->currentPage($request),
        );

        return $this->render('backoffice/report/index.html.twig', [
            'overview' => $reports->overview(),
            'payments' => $pagination->items,
            'pagination' => $pagination,
        ]);
    }
}
