<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AvatarUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authenticates API requests carrying a Google ID token as a bearer token.
 *
 * Unknown Google accounts are registered, like a Google login on the web: with ROLE_USER, so
 * they see no agent stats until an admin makes them agents.
 */
class GoogleApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const string BEARER_PREFIX = 'Bearer ';

    public function __construct(
        private readonly GoogleIdTokenVerifierInterface $idTokenVerifier,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AvatarUpdater $avatarUpdater,
    ) {
    }

    #[\Override]
    public function supports(Request $request): bool
    {
        return \str_starts_with((string) $request->headers->get('Authorization'), self::BEARER_PREFIX);
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $idToken = \trim(\substr((string) $request->headers->get('Authorization'), \strlen(self::BEARER_PREFIX)));

        if ('' === $idToken) {
            throw new CustomUserMessageAuthenticationException('Missing ID token.');
        }

        $payload = $this->idTokenVerifier->verify($idToken);

        if (null === $payload) {
            throw new CustomUserMessageAuthenticationException('Invalid ID token.');
        }

        if (true !== ($payload['email_verified'] ?? false)) {
            throw new CustomUserMessageAuthenticationException('Email address is not verified.');
        }

        $googleId = \is_string($payload['sub'] ?? null) ? $payload['sub'] : '';
        $email = \is_string($payload['email'] ?? null) ? $payload['email'] : '';

        if ('' === $googleId || '' === $email) {
            throw new CustomUserMessageAuthenticationException('Invalid ID token.');
        }

        return new SelfValidatingPassport(
            new UserBadge($googleId, function () use ($googleId, $email, $payload): User {
                $user = $this->findUser($googleId, $email);
                // The app signs in with Google only: its users get their picture here
                $this->avatarUpdater->update($user, $payload['picture'] ?? null);

                return $user;
            })
        );
    }

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    #[\Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return $this->unauthorized(\strtr($exception->getMessageKey(), $exception->getMessageData()));
    }

    #[\Override]
    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse
    {
        return $this->unauthorized('Authentication required.');
    }

    private function findUser(string $googleId, string $email): User
    {
        $user = $this->userRepository->findOneBy(['googleId' => $googleId]);

        if (null !== $user) {
            return $user;
        }

        // Link an existing account with the same email to this Google account, or register a new user
        $user = $this->userRepository->findOneBy(['identifier' => $email])
            ?? new User()->setIdentifier($email);

        $user->setGoogleId($googleId);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * An RFC 7807 problem, like API Platform's own errors.
     */
    private function unauthorized(string $message): JsonResponse
    {
        return new JsonResponse(
            [
                'type' => '/errors/401',
                'title' => 'An error occurred',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => $message,
            ],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => 'Bearer', 'Content-Type' => 'application/problem+json'],
        );
    }
}
