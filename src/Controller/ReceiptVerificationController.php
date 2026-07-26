<?php

namespace App\Controller;

use App\Entity\Receipt;
use App\Repository\ReceiptRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReceiptVerificationController extends AbstractController
{
    #[Route('/receipt/verify/{code}', name: 'receipt_verify')]
    public function verify(string $code, ReceiptRepository $receipts): Response
    {
        $receipt = $receipts->findOneBy(['verificationCode' => strtoupper($code)]);

        return $this->render('receipt/verify.html.twig', [
            'receipt' => $receipt,
            'code' => $code,
            'maskedStudent' => $receipt instanceof Receipt ? $this->maskStudentName($receipt) : null,
        ]);
    }

    /**
     * La page est publique : le nom complet et le matricule de l'eleve n'y ont
     * pas leur place. On affiche le prenom et l'initiale du nom, ce qui suffit
     * a celui qui tient le recu pour confirmer qu'il correspond, sans exposer
     * l'identite d'un enfant a qui saisirait un code au hasard.
     */
    private function maskStudentName(Receipt $receipt): string
    {
        $student = $receipt->getPayment()?->getStudent();
        if ($student === null) {
            return '';
        }

        $lastName = trim($student->getLastName());

        return trim($student->getFirstName() . ' ' . (
            $lastName === '' ? '' : mb_strtoupper(mb_substr($lastName, 0, 1)) . '.'
        ));
    }
}
