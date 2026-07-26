<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

/**
 * Droits d'ecriture du back-office.
 *
 * La lecture reste ouverte a ROLE_ADMIN, ROLE_COMPTABLE et ROLE_DIRECTEUR via
 * security.yaml. Ce voter ne tranche que la modification, car les trois roles
 * n'ont pas les memes pouvoirs :
 *  - l'administrateur gere le referentiel (annees, classes, eleves, frais...) ;
 *  - le comptable encaisse et annule, mais ne modifie pas le referentiel ;
 *  - le directeur est en consultation seule.
 *
 * Utiliser la meme regle dans les controleurs et dans Twig
 * (is_granted('MANAGE', 'students')) evite qu'un bouton s'affiche alors que
 * l'action serait refusee.
 */
class BackofficeVoter extends Voter
{
    public const MANAGE = 'MANAGE';

    public const PAYMENTS = 'payments';

    /** @var array<string, string[]> */
    private const WRITE_ROLES = [
        'school-years' => ['ROLE_ADMIN'],
        'classes' => ['ROLE_ADMIN'],
        'students' => ['ROLE_ADMIN'],
        'parents' => ['ROLE_ADMIN'],
        'fee-types' => ['ROLE_ADMIN'],
        'fee-assignments' => ['ROLE_ADMIN'],
        'discounts' => ['ROLE_ADMIN'],
        'student-discounts' => ['ROLE_ADMIN'],
        'users' => ['ROLE_ADMIN'],
        'settings' => ['ROLE_ADMIN'],
        self::PAYMENTS => ['ROLE_ADMIN', 'ROLE_COMPTABLE'],
    ];

    public function __construct(private Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE
            && is_string($subject)
            && isset(self::WRITE_ROLES[$subject]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$token->getUser() instanceof User) {
            return false;
        }

        foreach (self::WRITE_ROLES[$subject] as $role) {
            // On delegue a Security plutot que de lire les roles du token :
            // une eventuelle hierarchie de roles reste ainsi respectee.
            if ($this->security->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
