<?php

namespace App\Controller\Backoffice;

use App\Entity\Receipt;
use App\Repository\ReceiptRepository;
use App\Repository\SchoolSettingRepository;
use App\Service\AmountInWords;
use App\Service\PaginationService;
use App\Service\PaymentCalculationService;
use App\Service\QrCodeGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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

    #[Route('/{id}/print', name: 'backoffice_receipt_print', requirements: ['id' => '\d+'])]
    public function print(
        int $id,
        ReceiptRepository $receipts,
        SchoolSettingRepository $settings,
        PaymentCalculationService $calculator,
        QrCodeGenerator $qrCodes,
        AmountInWords $amountInWords,
    ): Response {
        $receipt = $receipts->find($id);
        if (!$receipt instanceof Receipt) {
            throw $this->createNotFoundException();
        }

        $verificationUrl = $this->generateUrl(
            'receipt_verify',
            ['code' => $receipt->getVerificationCode()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $this->render('receipt/print.html.twig', [
            'receipt' => $receipt,
            'settings' => $settings->getSettings(),
            'situation' => $calculator->getStudentSituation($receipt->getPayment()->getStudent()),
            'qrCode' => $qrCodes->dataUri($verificationUrl),
            'amountInWords' => $amountInWords->format((float) $receipt->getPayment()->getTotalAmount()),
            'backPath' => $this->generateUrl('backoffice_receipt_index'),
        ]);
    }
}
