<?php

namespace App\Repository;

use App\Entity\SchoolSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SchoolSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, SchoolSetting::class); }

    public function getSettings(): SchoolSetting
    {
        $settings = $this->findOneBy([]);
        if ($settings) {
            return $settings;
        }

        $settings = new SchoolSetting();
        $this->getEntityManager()->persist($settings);
        $this->getEntityManager()->flush();

        return $settings;
    }
}
