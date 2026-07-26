<?php

namespace App\Repository;

use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class StudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Student::class); }

    public function search(?string $query = null, array $filters = []): array
    {
        return $this->searchQueryBuilder($query, $filters)->getQuery()->getResult();
    }

    public function searchQueryBuilder(?string $query = null, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.schoolClass', 'c')->addSelect('c')
            ->leftJoin('s.parents', 'p')->addSelect('p')
            ->orderBy('s.lastName', 'ASC');

        if ($query) {
            $qb->andWhere('LOWER(s.registrationNumber) LIKE :q OR LOWER(s.firstName) LIKE :q OR LOWER(s.lastName) LIKE :q OR LOWER(p.phone) LIKE :q')
                ->setParameter('q', '%' . strtolower($query) . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('s.status = :status')->setParameter('status', $filters['status']);
        }

        if (!empty($filters['class'])) {
            $qb->andWhere('c.id = :class')->setParameter('class', $filters['class']);
        }

        return $qb;
    }
}
