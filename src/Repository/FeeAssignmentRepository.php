<?php

namespace App\Repository;

use App\Entity\FeeAssignment;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FeeAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, FeeAssignment::class); }

    public function findForStudent(Student $student): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.feeType', 'ft')->addSelect('ft')
            ->andWhere('f.student = :student OR (f.student IS NULL AND f.schoolClass = :class)')
            ->setParameter('student', $student)
            ->setParameter('class', $student->getSchoolClass())
            ->orderBy('f.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
