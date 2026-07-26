<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Notification::class); }

    /**
     * Une notification portant cette reference a-t-elle deja ete envoyee a cet
     * utilisateur ?
     *
     * On interroge aussi les entites en attente de flush : la commande
     * d'echeances traite plusieurs eleves d'affilee et pourrait sinon produire
     * deux fois le meme avis avant le premier enregistrement.
     */
    public function existsFor(User $user, string $reference): bool
    {
        foreach ($this->getEntityManager()->getUnitOfWork()->getScheduledEntityInsertions() as $pending) {
            if ($pending instanceof Notification
                && $pending->getUser() === $user
                && $pending->getReference() === $reference) {
                return true;
            }
        }

        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.reference = :reference')
            ->setParameter('user', $user)
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function countUnread(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
