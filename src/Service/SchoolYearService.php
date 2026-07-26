<?php

namespace App\Service;

use App\Entity\SchoolYear;
use App\Repository\SchoolYearRepository;
use Doctrine\ORM\EntityManagerInterface;

class SchoolYearService
{
    public function __construct(private SchoolYearRepository $schoolYears, private EntityManagerInterface $entityManager)
    {
    }

    public function activate(SchoolYear $schoolYear): void
    {
        foreach ($this->schoolYears->findBy(['isActive' => true]) as $activeYear) {
            $activeYear->setIsActive(false);
        }

        $schoolYear->setIsActive(true);
        $schoolYear->touch();
        $this->entityManager->flush();
    }
}
