<?php

namespace App\Repository;

use App\Entity\SchoolYear;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SchoolYearRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, SchoolYear::class); }
    public function findActive(): ?SchoolYear { return $this->findOneBy(['isActive' => true]); }
}
