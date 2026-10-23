<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class GoogleIdentityAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly GoogleIdTokenVerifierInterface $idTokenVerifier,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ApiTokenStore $apiTokenStore,
    ) {
    }

    #[\Override]
    public function supports(Request $request): bool
    {
        return '/connect/google/verify' === $request->getPathInfo();
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $idToken = (string) $request->request->get('credential');

        if ('' === $idToken || '0' === $idToken) {
            throw new AuthenticationException('Missing credentials :(');
        }

        $payload = $this->idTokenVerifier->verify($idToken);

        if (null === $payload) {
            throw new AuthenticationException('Invalid ID token :(');
        }

        $googleId = \is_string($payload['sub'] ?? null) ? $payload['sub'] : '';
        $email = \is_string($payload['email'] ?? null) ? $payload['email'] : '';

        if ('' === $googleId || '' === $email) {
            throw new AuthenticationException('Invalid ID token :(');
        }

        $user = $this->getUser($googleId, $email);
        $this->apiTokenStore->store($request->getSession(), $idToken, $payload);

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier()),
            [new RememberMeBadge()],
        );
    }

    #[\Override]
    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): RedirectResponse {
        if ($targetPath = $this->getTargetPath(
            $request->getSession(),
            $firewallName
        )
        ) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_default'));
    }

    #[\Override]
    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): RedirectResponse {
        $message = \strtr(
            $exception->getMessageKey(),
            $exception->getMessageData()
        );

        /**
         * @var Session $session
         */
        $session = $request->getSession();
        $session->getFlashBag()->add('danger', $message);

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function getUser(string $googleId, string $email): User
    {
        $user = $this->userRepository->findOneBy(['googleId' => $googleId]);

        if (null !== $user) {
            return $user;
        }

        // Link to existing account with same email, or register a new user
        $user = $this->userRepository->findOneBy(['identifier' => $email])
            ?? new User()->setIdentifier($email);

        $user->setGoogleId($googleId);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
