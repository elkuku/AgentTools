<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Security\GoogleUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

#[AllowMockObjectsWithoutExpectations]
class GoogleUserResolverTest extends TestCase
{
    private UserRepository&MockObject $userRepository;

    private EntityManagerInterface&MockObject $entityManager;

    private GoogleUserResolver $resolver;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->resolver = new GoogleUserResolver($this->userRepository, $this->entityManager);
    }

    public function testIdentityReturnsGoogleIdAndEmail(): void
    {
        self::assertSame(
            ['gid-1', 'a@example.com'],
            GoogleUserResolver::identity(['sub' => 'gid-1', 'email' => 'a@example.com', 'email_verified' => true]),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testIdentityRejects(array $payload, string $message): void
    {
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage($message);
        GoogleUserResolver::identity($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'unverified email' => [['sub' => 'gid-1', 'email' => 'a@example.com', 'email_verified' => false], 'Email address is not verified.'];
        yield 'no email_verified' => [['sub' => 'gid-1', 'email' => 'a@example.com'], 'Email address is not verified.'];
        yield 'no email' => [['sub' => 'gid-1', 'email_verified' => true], 'Invalid ID token.'];
        yield 'no sub' => [['email' => 'a@example.com', 'email_verified' => true], 'Invalid ID token.'];
    }

    public function testResolveReturnsExistingUserByGoogleId(): void
    {
        $existingUser = new User()->setIdentifier('test@example.com')->setGoogleId('gid-123');

        $this->userRepository->expects(self::once())->method('findOneBy')
            ->with(['googleId' => 'gid-123'])
            ->willReturn($existingUser);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        self::assertSame($existingUser, $this->resolver->resolve('gid-123', 'test@example.com'));
    }

    public function testResolveLinksExistingAccountByEmail(): void
    {
        $existingUser = new User()->setIdentifier('shared@example.com');

        $this->userRepository->method('findOneBy')
            ->willReturnMap([
                [['googleId' => 'gid-999'], null],
                [['identifier' => 'shared@example.com'], $existingUser],
            ]);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->resolver->resolve('gid-999', 'shared@example.com');

        self::assertSame($existingUser, $result);
        self::assertSame('gid-999', $result->getGoogleId());
    }

    public function testResolveCreatesNewUserWhenNotFound(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->resolver->resolve('gid-999', 'brand@new.com');

        self::assertSame('brand@new.com', $result->getIdentifier());
        self::assertSame('gid-999', $result->getGoogleId());
        self::assertSame(UserRole::USER, $result->getRole());
    }
}
