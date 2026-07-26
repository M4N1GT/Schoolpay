<?php

namespace App\Controller\Backoffice;

use App\Repository\ReceiptRepository;
use App\Repository\SchoolSettingRepository;
use App\Service\PaginationService;
use App\Service\PaymentCalculationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/backoffice/receipts')]
class ReceiptController extends AbstractController
{
    #[Route('', name: 'backoffice_receipt_index')]
    public function index(Request $request, ReceiptRepository $receipts, PaginationService $paginator): Response
    {
        $pagination = $paginator->paginate(
            $receipts->createQueryBuilder('r')
                ->leftJoin('r.payment', 'p')->addSelect('p')
                ->leftJoin('p.student', 's')->addSelect('s')
                ->orderBy('r.generatedAt', 'DESC'),
            $paginator->currentPage($request),
        );

        return $this->render('backoffice/receipt/index.html.twig', [
            'receipts' => $pagination->items,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/{id}/print', name: 'backoffice_receipt_print')]
    public function print(int $id, ReceiptRepository $receipts, SchoolSettingRepository $settings, PaymentCalculationService $calculator): Response
    {
        $receipt = $receipts->find($id);
        if (!$receipt) {
            throw $this->createNotFoundException();
        }

        return $this->render('receipt/print.html.twig', [
            'receipt' => $receipt,
            'settings' => $settings->getSettings(),
            'situation' => $calculator->getStudentSituation($receipt->getPayment()->getStudent()),
        ]);
    }
}
