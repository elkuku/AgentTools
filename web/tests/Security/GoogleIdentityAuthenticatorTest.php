<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Security\ApiTokenStore;
use App\Security\GoogleIdentityAuthenticator;
use App\Security\GoogleIdTokenVerifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[AllowMockObjectsWithoutExpectations]
class GoogleIdentityAuthenticatorTest extends TestCase
{
    private GoogleIdentityAuthenticator $authenticator;

    private UserRepository&MockObject $userRepository;

    private EntityManagerInterface&MockObject $entityManager;

    private UrlGeneratorInterface&MockObject $urlGenerator;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->authenticator = new GoogleIdentityAuthenticator(
            $this->createStub(GoogleIdTokenVerifierInterface::class),
            $this->userRepository,
            $this->entityManager,
            $this->urlGenerator,
            new ApiTokenStore(false),
        );
    }

    public function testSupportsGoogleVerifyPath(): void
    {
        self::assertTrue($this->authenticator->supports(Request::create('/connect/google/verify')));
    }

    public function testDoesNotSupportOtherPaths(): void
    {
        self::assertFalse($this->authenticator->supports(Request::create('/')));
        self::assertFalse($this->authenticator->supports(Request::create('/connect/google/check')));
    }

    public function testAuthenticateThrowsOnEmptyCredential(): void
    {
        $request = new Request([], ['credential' => '']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Missing credentials');
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnZeroCredential(): void
    {
        $request = new Request([], ['credential' => '0']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Missing credentials');
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsWithoutEmail(): void
    {
        $verifier = $this->createStub(GoogleIdTokenVerifierInterface::class);
        $verifier->method('verify')->willReturn(['sub' => 'gid-123']);
        $authenticator = new GoogleIdentityAuthenticator(
            $verifier,
            $this->userRepository,
            $this->entityManager,
            $this->urlGenerator,
            new ApiTokenStore(false),
        );
        $request = new Request([], ['credential' => 'token']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token');
        $authenticator->authenticate($request);
    }

    public function testGetUserReturnsExistingUserByGoogleId(): void
    {
        $existingUser = new User()->setIdentifier('test@example.com')->setGoogleId('gid-123');

        $this->userRepository->expects(self::once())->method('findOneBy')
            ->with(['googleId' => 'gid-123'])
            ->willReturn($existingUser);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, 'gid-123', 'test@example.com');

        self::assertSame($existingUser, $result);
    }

    public function testGetUserLinksExistingAccountByEmail(): void
    {
        $existingUser = new User()->setIdentifier('shared@example.com');

        $this->userRepository->method('findOneBy')
            ->willReturnMap([
                [['googleId' => 'gid-999'], null],
                [['identifier' => 'shared@example.com'], $existingUser],
            ]);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, 'gid-999', 'shared@example.com');

        self::assertSame($existingUser, $result);
        self::assertSame('gid-999', $result->getGoogleId());
    }

    public function testGetUserCreatesNewUserWhenNotFound(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, 'gid-999', 'brand@new.com');

        self::assertSame('brand@new.com', $result->getIdentifier());
        self::assertSame('gid-999', $result->getGoogleId());
        // New users wait for an admin to make them agents
        self::assertSame(UserRole::USER, $result->getRole());
    }

    public function testOnAuthenticationSuccessRedirectsToTargetPath(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.main.target_path', '/profile');

        $request = new Request();
        $request->setSession($session);

        $response = $this->authenticator->onAuthenticationSuccess(
            $request,
            $this->createStub(TokenInterface::class),
            'main'
        );

        self::assertSame('/profile', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessRedirectsToDefault(): void
    {
        $this->urlGenerator->expects(self::once())->method('generate')->with('app_default')->willReturn('/');

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $this->authenticator->onAuthenticationSuccess(
            $request,
            $this->createStub(TokenInterface::class),
            'main'
        );

        self::assertSame('/', $response->getTargetUrl());
    }

    public function testOnAuthenticationFailureAddsFlashAndRedirects(): void
    {
        $this->urlGenerator->expects(self::once())->method('generate')->with('app_login')->willReturn('/login');

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $this->authenticator->onAuthenticationFailure(
            $request,
            new AuthenticationException('Invalid token.')
        );

        self::assertSame('/login', $response->getTargetUrl());
    }
}
