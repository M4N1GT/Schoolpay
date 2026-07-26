<?php

namespace App\Controller\Backoffice;

use App\Repository\StudentRepository;
use App\Service\PaginationService;
use App\Service\PaymentCalculationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OutstandingPaymentController extends AbstractController
{
    #[Route('/backoffice/outstanding', name: 'backoffice_outstanding_index')]
    public function index(Request $request, StudentRepository $students, PaymentCalculationService $calculator, PaginationService $paginator): Response
    {
        $query = trim((string) $request->query->get('q', ''));

        // On pagine d'abord les eleves, puis on ne calcule la situation que
        // pour la page affichee : le cout ne depend plus de l'effectif total.
        //
        // Contrepartie assumee : le filtre "reste a payer > 0" s'applique apres
        // la pagination, donc une page peut compter moins de lignes que la
        // taille de page. Supprimer cette limite suppose d'agreger les soldes
        // en base (OutstandingBalanceService, section 27 du cahier des charges).
        $pagination = $paginator->paginate(
            $students->searchQueryBuilder($query ?: null, $request->query->all()),
            $paginator->currentPage($request),
        );

        $rows = [];
        foreach ($pagination->items as $student) {
            $situation = $calculator->getStudentSituation($student);
            if ($situation['remainingTotal'] > 0) {
                $rows[] = ['student' => $student, 'situation' => $situation];
            }
        }

        return $this->render('backoffice/outstanding/index.html.twig', [
            'rows' => $rows,
            'pagination' => $pagination,
            'query' => $query,
        ]);
    }
}
