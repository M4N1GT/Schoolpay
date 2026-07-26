<?php

namespace App\Controller\Backoffice;

use App\Repository\AuditLogRepository;
use App\Service\PaginationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuditLogController extends AbstractController
{
    #[Route('/backoffice/audit', name: 'backoffice_audit_index')]
    public function index(Request $request, AuditLogRepository $audits, PaginationService $paginator): Response
    {
        // La limite fixe de 100 lignes masquait purement et simplement le reste
        // du journal : la pagination rend l'historique complet accessible.
        $pagination = $paginator->paginate(
            $audits->createQueryBuilder('a')
                ->leftJoin('a.user', 'u')->addSelect('u')
                ->orderBy('a.createdAt', 'DESC'),
            $paginator->currentPage($request),
        );

        return $this->render('backoffice/audit/index.html.twig', [
            'audits' => $pagination->items,
            'pagination' => $pagination,
        ]);
    }
}
