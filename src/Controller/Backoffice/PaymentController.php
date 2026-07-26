<?php

namespace App\Controller\Backoffice;

use App\Entity\FeeAssignment;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Repository\StudentRepository;
use App\Security\Voter\BackofficeVoter;
use App\Service\ListFilterService;
use App\Service\PaginationService;
use App\Service\PaymentCalculationService;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/backoffice/payments')]
class PaymentController extends AbstractController
{
    /** Sert a la fois le formulaire de caisse et le filtre de la liste. */
    public const PAYMENT_METHODS = [
        'especes', 'MVola', 'Orange Money', 'Airtel Money',
        'virement bancaire', 'carte bancaire', 'cheque', 'autre',
    ];

    #[Route('', name: 'backoffice_payment_index')]
    public function index(Request $request, PaymentRepository $payments, PaginationService $paginator, ListFilterService $filters): Response
    {
        $queryBuilder = $payments->createQueryBuilder('p')
            ->leftJoin('p.student', 's')->addSelect('s');

        $filters->applySearch($queryBuilder, ['p.paymentNumber', 'p.externalReference', 's.registrationNumber', 's.firstName', 's.lastName'], $request->query->get('q'));
        $filters->applyExact($queryBuilder, 'p.paymentMethod', $request->query->get('method'), 'method');
        $filters->applyExact($queryBuilder, 'p.status', $request->query->get('status'), 'status');
        $filters->applyDateRange($queryBuilder, 'p.paymentDate', $request->query->get('from'), $request->query->get('to'));
        $filters->applySort($queryBuilder, [
            'date' => 'p.paymentDate',
            'montant' => 'p.totalAmount',
            'eleve' => 's.lastName',
        ], $request, 'p.paymentDate', 'DESC');

        $pagination = $paginator->paginate($queryBuilder, $paginator->currentPage($request));

        return $this->render('backoffice/payment/index.html.twig', [
            'payments' => $pagination->items,
            'pagination' => $pagination,
            'methods' => self::PAYMENT_METHODS,
            'statuses' => [Payment::STATUS_VALIDATED, Payment::STATUS_PARTIAL, Payment::STATUS_CANCELLED],
            'hasFilters' => $filters->hasActiveFilters($request),
        ]);
    }

    #[Route('/new', name: 'backoffice_payment_new')]
    public function search(Request $request, StudentRepository $students): Response
    {
        $this->denyAccessUnlessGranted(BackofficeVoter::MANAGE, BackofficeVoter::PAYMENTS);
        $query = trim((string) $request->query->get('q', ''));

        return $this->render('backoffice/payment/search.html.twig', [
            'query' => $query,
            'students' => $query ? $students->search($query) : [],
        ]);
    }

    #[Route('/student/{id}', name: 'backoffice_payment_student')]
    public function studentPayment(int $id, Request $request, StudentRepository $students, PaymentCalculationService $calculator, PaymentService $paymentService): Response
    {
        $student = $students->find($id);
        if (!$student) {
            throw $this->createNotFoundException();
        }

        $situation = $calculator->getStudentSituation($student);

        // La consultation de la situation reste ouverte au directeur ; seul
        // l'encaissement est reserve a l'administrateur et au comptable.
        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted(BackofficeVoter::MANAGE, BackofficeVoter::PAYMENTS);

            if (!$this->isCsrfTokenValid('payment_' . $student->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $selectedIds = array_map('intval', $request->request->all('fees'));
            $assignments = array_values(array_filter(array_map(
                fn (array $item): ?FeeAssignment => in_array($item['fee']->getId(), $selectedIds, true) ? $item['fee'] : null,
                $situation['items']
            )));

            try {
                $payment = $paymentService->registerPayment(
                    $student,
                    $assignments,
                    (float) $request->request->get('amount'),
                    (string) $request->request->get('paymentMethod'),
                    $request->request->get('externalReference') ?: null,
                    $request->request->get('notes') ?: null,
                    $this->getUser() instanceof User ? $this->getUser() : null,
                );
                $this->addFlash('success', 'Paiement enregistre et recu genere.');

                return $this->redirectToRoute('backoffice_receipt_print', ['id' => $payment->getReceipt()?->getId()]);
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('backoffice/payment/student.html.twig', [
            'student' => $student,
            'situation' => $situation,
            'methods' => self::PAYMENT_METHODS,
        ]);
    }

    #[Route('/{id}/cancel', name: 'backoffice_payment_cancel', methods: ['POST'])]
    public function cancel(int $id, Request $request, PaymentRepository $payments, PaymentService $paymentService): Response
    {
        $this->denyAccessUnlessGranted(BackofficeVoter::MANAGE, BackofficeVoter::PAYMENTS);
        $payment = $payments->find($id);
        if (!$payment) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('cancel_payment_' . $payment->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $paymentService->cancelPayment($payment, (string) $request->request->get('reason'), $this->getUser() instanceof User ? $this->getUser() : null);
        $this->addFlash('success', 'Paiement annule avec justification.');

        return $this->redirectToRoute('backoffice_payment_index');
    }
}
