<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\GithubResourceOwner;
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

class GitHubAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[\Override]
    public function supports(Request $request): bool
    {
        return '/connect/check/github' === $request->getPathInfo();
    }

    /**
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $token = $this->getClient()->getAccessToken();

        /** @var GithubResourceOwner $githubResourceOwner */
        $githubResourceOwner = $this->getClient()
            ->fetchUserFromToken($token);

        $user = $this->getUser($githubResourceOwner);

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier()),
            [new RememberMeBadge()],
        );
    }

    private function getClient(): OAuth2ClientInterface
    {
        return $this->clientRegistry->getClient('github');
    }

    private function getUser(GithubResourceOwner $resourceOwner): User
    {
        // 1) have they logged in with GitHub before? Easy!
        if (($user = $this->userRepository->findOneBy(
            ['gitHubId' => $resourceOwner->getId()]
        )) !== null
        ) {
            return $user;
        }

        // Link to existing account with same nickname, or register a new user
        $user = $this->userRepository->findOneBy(['identifier' => $resourceOwner->getNickname()])
            ?? new User()->setIdentifier((string) $resourceOwner->getNickname());

        $user->setGitHubId($resourceOwner->getId());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
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
