<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, AuditLog::class); }

    /**
     * Valeurs distinctes d'une colonne, pour alimenter les listes deroulantes
     * de filtrage sans figer la liste dans le code : les actions auditees
     * evoluent avec l'application.
     *
     * @return string[]
     */
    public function distinctValues(string $field): array
    {
        if (!in_array($field, ['action', 'entityType'], true)) {
            throw new \InvalidArgumentException('Colonne non filtrable : ' . $field);
        }

        $rows = $this->createQueryBuilder('a')
            ->select('DISTINCT a.' . $field . ' AS value')
            ->orderBy('a.' . $field, 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_column($rows, 'value')));
    }
}
