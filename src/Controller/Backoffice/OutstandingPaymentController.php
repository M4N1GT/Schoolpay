<?php

namespace App\Controller\Backoffice;

use App\Repository\StudentRepository;
use App\Service\PaymentCalculationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OutstandingPaymentController extends AbstractController
{
    #[Route('/backoffice/outstanding', name: 'backoffice_outstanding_index')]
    public function index(StudentRepository $students, PaymentCalculationService $calculator): Response
    {
        $rows = [];
        foreach ($students->findBy([], ['lastName' => 'ASC']) as $student) {
            $situation = $calculator->getStudentSituation($student);
            if ($situation['remainingTotal'] > 0) {
                $rows[] = ['student' => $student, 'situation' => $situation];
            }
        }

        return $this->render('backoffice/outstanding/index.html.twig', ['rows' => $rows]);
    }
}
