<?php

namespace App\Security;

use App\Service\AvatarUpdater;
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

    public const string CSRF_TOKEN = 'g_csrf_token';

    public function __construct(
        private readonly GoogleIdTokenVerifierInterface $idTokenVerifier,
        private readonly GoogleUserResolver $userResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ApiTokenStore $apiTokenStore,
        private readonly AvatarUpdater $avatarUpdater,
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
        // Google's double-submit cookie: the button posts the same g_csrf_token as a cookie and a field
        $csrfCookie = (string) $request->cookies->get(self::CSRF_TOKEN);
        $csrfField = (string) $request->request->get(self::CSRF_TOKEN);

        if ('' === $csrfCookie || !\hash_equals($csrfCookie, $csrfField)) {
            throw new AuthenticationException('Invalid CSRF token :(');
        }

        $idToken = (string) $request->request->get('credential');

        if ('' === $idToken || '0' === $idToken) {
            throw new AuthenticationException('Missing credentials :(');
        }

        $payload = $this->idTokenVerifier->verify($idToken);

        if (null === $payload) {
            throw new AuthenticationException('Invalid ID token :(');
        }

        [$googleId, $email] = GoogleUserResolver::identity($payload);

        $user = $this->userResolver->resolve($googleId, $email);
        $this->avatarUpdater->update($user, $payload['picture'] ?? null);
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
}
