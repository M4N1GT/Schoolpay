<?php

namespace App\Controller\Backoffice;

use App\Repository\AuditLogRepository;
use App\Repository\UserRepository;
use App\Service\ListFilterService;
use App\Service\PaginationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuditLogController extends AbstractController
{
    #[Route('/backoffice/audit', name: 'backoffice_audit_index')]
    public function index(
        Request $request,
        AuditLogRepository $audits,
        UserRepository $users,
        PaginationService $paginator,
        ListFilterService $filters,
    ): Response {
        $queryBuilder = $audits->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')->addSelect('u');

        $filters->applySearch($queryBuilder, ['a.description', 'a.entityType'], $request->query->get('q'));
        $filters->applyExact($queryBuilder, 'a.action', $request->query->get('action'), 'action');
        $filters->applyExact($queryBuilder, 'a.entityType', $request->query->get('entity'), 'entity');
        $filters->applyExact($queryBuilder, 'u.id', $request->query->get('user'), 'user');
        $filters->applyDateRange($queryBuilder, 'a.createdAt', $request->query->get('from'), $request->query->get('to'));
        $filters->applySort($queryBuilder, [
            'date' => 'a.createdAt',
            'action' => 'a.action',
            'entite' => 'a.entityType',
        ], $request, 'a.createdAt', 'DESC');

        $pagination = $paginator->paginate($queryBuilder, $paginator->currentPage($request));

        return $this->render('backoffice/audit/index.html.twig', [
            'audits' => $pagination->items,
            'pagination' => $pagination,
            'actions' => $audits->distinctValues('action'),
            'entities' => $audits->distinctValues('entityType'),
            'users' => $users->findBy([], ['lastName' => 'ASC']),
            'hasFilters' => $filters->hasActiveFilters($request),
        ]);
    }
}
