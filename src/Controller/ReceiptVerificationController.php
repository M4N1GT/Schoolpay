<?php

namespace App\Controller;

use App\Repository\ReceiptRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReceiptVerificationController extends AbstractController
{
    #[Route('/receipt/verify/{code}', name: 'receipt_verify')]
    public function verify(string $code, ReceiptRepository $receipts): Response
    {
        return $this->render('receipt/verify.html.twig', [
            'receipt' => $receipts->findOneBy(['verificationCode' => strtoupper($code)]),
            'code' => $code,
        ]);
    }
}
