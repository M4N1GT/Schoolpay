<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $users,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $email = strtolower((string) $request->request->get('email', ''));
        $request->getSession()->set(Security::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email, function (string $userIdentifier): ?User {
                $user = $this->users->findOneBy(['email' => $userIdentifier, 'isActive' => true]);

                return $user instanceof User ? $user : null;
            }),
            new PasswordCredentials((string) $request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        $user = $token->getUser();
        if ($user instanceof User) {
            $user->setLastLoginAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            if (in_array('ROLE_PARENT', $user->getRoles(), true) && count(array_intersect($user->getRoles(), ['ROLE_ADMIN', 'ROLE_COMPTABLE', 'ROLE_DIRECTEUR'])) === 0) {
                return new RedirectResponse($this->urlGenerator->generate('parent_dashboard'));
            }
        }

        return new RedirectResponse($this->urlGenerator->generate('backoffice_dashboard'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('app_login');
    }
}
