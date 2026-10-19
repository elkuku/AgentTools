<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\GoogleIdentityAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\GoogleUser;
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
            'test-google-client-id',
            $this->userRepository,
            $this->entityManager,
            $this->urlGenerator,
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

    public function testGetUserReturnsExistingUserByGoogleId(): void
    {
        $existingUser = new User()->setIdentifier('test@example.com')->setGoogleId('gid-123');

        $this->userRepository->expects(self::once())->method('findOneBy')
            ->with(['googleId' => 'gid-123'])
            ->willReturn($existingUser);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $googleUser = $this->createStub(GoogleUser::class);
        $googleUser->method('getId')->willReturn('gid-123');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, $googleUser);

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

        $googleUser = $this->createStub(GoogleUser::class);
        $googleUser->method('getId')->willReturn('gid-999');
        $googleUser->method('getEmail')->willReturn('shared@example.com');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, $googleUser);

        self::assertSame($existingUser, $result);
        self::assertSame('gid-999', $result->getGoogleId());
    }

    public function testGetUserCreatesNewUserWhenNotFound(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $googleUser = $this->createStub(GoogleUser::class);
        $googleUser->method('getId')->willReturn('gid-999');
        $googleUser->method('getEmail')->willReturn('brand@new.com');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, $googleUser);

        self::assertSame('brand@new.com', $result->getIdentifier());
        self::assertSame('gid-999', $result->getGoogleId());
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
