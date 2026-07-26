<?php

namespace App\Controller\Frontoffice;

use App\Entity\Receipt;
use App\Entity\User;
use App\Repository\ReceiptRepository;
use App\Repository\SchoolSettingRepository;
use App\Service\AmountInWords;
use App\Service\PaymentCalculationService;
use App\Service\QrCodeGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Consultation et impression des recus depuis l'espace parent (section 16).
 *
 * La route d'impression du back-office est fermee aux parents ; sans cet
 * ecran, ils ne disposaient que de la page publique de verification, qui
 * n'affiche volontairement pas le detail des frais.
 */
#[Route('/parent/receipts')]
class ParentReceiptController extends AbstractController
{
    #[Route('/{id}', name: 'parent_receipt_show', requirements: ['id' => '\d+'])]
    public function show(
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

        $student = $receipt->getPayment()?->getStudent();
        $user = $this->getUser();
        $guardian = $user instanceof User ? $user->getParentProfile() : null;

        if (!$student || !$guardian || !$student->getParents()->contains($guardian)) {
            throw $this->createAccessDeniedException('Ce recu ne concerne aucun de vos enfants.');
        }

        return $this->render('receipt/print.html.twig', [
            'receipt' => $receipt,
            'settings' => $settings->getSettings(),
            'situation' => $calculator->getStudentSituation($student),
            'qrCode' => $qrCodes->dataUri($this->verificationUrl($receipt)),
            'amountInWords' => $amountInWords->format((float) $receipt->getPayment()->getTotalAmount()),
            'backPath' => $this->generateUrl('parent_child_show', ['id' => $student->getId()]),
        ]);
    }

    private function verificationUrl(Receipt $receipt): string
    {
        return $this->generateUrl(
            'receipt_verify',
            ['code' => $receipt->getVerificationCode()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
