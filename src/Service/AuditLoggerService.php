<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLoggerService
{
    public function __construct(private EntityManagerInterface $entityManager, private RequestStack $requestStack)
    {
    }

    public function log(string $action, string $entityType, ?int $entityId, string $description, ?User $user = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $log = new AuditLog();
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setDescription($description);
        $log->setUser($user);
        $log->setOldValues($oldValues);
        $log->setNewValues($newValues);
        $log->setIpAddress($request?->getClientIp());
        $log->setUserAgent($request?->headers->get('User-Agent'));

        $this->entityManager->persist($log);
    }
}
