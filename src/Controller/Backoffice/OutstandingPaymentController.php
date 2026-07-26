<?php

namespace App\Controller\Backoffice;

use App\Repository\SchoolClassRepository;
use App\Repository\StudentRepository;
use App\Service\ListFilterService;
use App\Service\PaginationService;
use App\Service\PaymentCalculationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OutstandingPaymentController extends AbstractController
{
    #[Route('/backoffice/outstanding', name: 'backoffice_outstanding_index')]
    public function index(
        Request $request,
        StudentRepository $students,
        SchoolClassRepository $schoolClasses,
        PaymentCalculationService $calculator,
        PaginationService $paginator,
        ListFilterService $filters,
    ): Response {
        $query = trim((string) $request->query->get('q', ''));
        $minimum = (float) $request->query->get('min', 0);

        // On pagine d'abord les eleves, puis on ne calcule la situation que
        // pour la page affichee : le cout ne depend plus de l'effectif total.
        //
        // Contrepartie assumee : le reste a payer n'existe qu'apres calcul, donc
        // le filtre sur le montant minimum s'applique apres la pagination et une
        // page peut compter moins de lignes que la taille de page. Supprimer
        // cette limite suppose d'agreger les soldes en base
        // (OutstandingBalanceService, section 27 du cahier des charges).
        $pagination = $paginator->paginate(
            $students->searchQueryBuilder($query ?: null, $request->query->all()),
            $paginator->currentPage($request),
        );

        $rows = [];
        foreach ($pagination->items as $student) {
            $situation = $calculator->getStudentSituation($student);
            if ($situation['remainingTotal'] > 0 && $situation['remainingTotal'] >= $minimum) {
                $rows[] = [
                    'student' => $student,
                    'situation' => $situation,
                    'overdueDays' => $this->overdueDays($situation),
                ];
            }
        }

        return $this->render('backoffice/outstanding/index.html.twig', [
            'rows' => $rows,
            'pagination' => $pagination,
            'query' => $query,
            'classes' => $schoolClasses->findBy([], ['name' => 'ASC']),
            'levels' => $this->levels($schoolClasses),
            'hasFilters' => $filters->hasActiveFilters($request),
        ]);
    }

    /**
     * Nombre de jours de retard de l'echeance impayee la plus ancienne
     * (section 17).
     */
    private function overdueDays(array $situation): int
    {
        $today = new \DateTimeImmutable('today');
        $oldest = null;

        foreach ($situation['items'] as $item) {
            $dueDate = $item['fee']->getDueDate();
            if ($item['remaining'] > 0 && $dueDate && $dueDate < $today && ($oldest === null || $dueDate < $oldest)) {
                $oldest = $dueDate;
            }
        }

        return $oldest === null ? 0 : (int) $today->diff($oldest)->days;
    }

    /** @return string[] */
    private function levels(SchoolClassRepository $schoolClasses): array
    {
        $levels = array_map(
            static fn (object $schoolClass): string => $schoolClass->getLevel(),
            $schoolClasses->findBy([], ['level' => 'ASC'])
        );

        return array_values(array_unique(array_filter($levels)));
    }
}
