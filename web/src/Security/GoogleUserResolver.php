<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * The user of a verified Google ID token, for the web sign-in and the API alike.
 */
final readonly class GoogleUserResolver
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * The token's Google ID and email: both required, and the email must be verified, as it may
     * link an existing account.
     *
     * @param array<string, mixed> $payload the verified token payload
     *
     * @return array{string, string}
     *
     * @throws CustomUserMessageAuthenticationException
     */
    public static function identity(array $payload): array
    {
        if (true !== ($payload['email_verified'] ?? false)) {
            throw new CustomUserMessageAuthenticationException('Email address is not verified.');
        }

        $googleId = \is_string($payload['sub'] ?? null) ? $payload['sub'] : '';
        $email = \is_string($payload['email'] ?? null) ? $payload['email'] : '';

        if ('' === $googleId || '' === $email) {
            throw new CustomUserMessageAuthenticationException('Invalid ID token.');
        }

        return [$googleId, $email];
    }

    /**
     * The user with this Google ID; else links the account with this email to it, or registers a
     * new user (ROLE_USER).
     */
    public function resolve(string $googleId, string $email): User
    {
        $user = $this->userRepository->findOneBy(['googleId' => $googleId]);

        if (null !== $user) {
            return $user;
        }

        $user = $this->userRepository->findOneBy(['identifier' => $email])
            ?? new User()->setIdentifier($email);

        $user->setGoogleId($googleId);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
