<?php

namespace App\Controller\Backoffice;

use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuditLogController extends AbstractController
{
    #[Route('/backoffice/audit', name: 'backoffice_audit_index')]
    public function index(AuditLogRepository $audits): Response
    {
        return $this->render('backoffice/audit/index.html.twig', [
            'audits' => $audits->findBy([], ['createdAt' => 'DESC'], 100),
        ]);
    }
}
