<?php

namespace App\Tests\Security;

use App\Repository\UserRepository;
use App\Security\ApiTokenStore;
use App\Security\GoogleIdentityAuthenticator;
use App\Security\GoogleIdTokenVerifierInterface;
use App\Security\GoogleUserResolver;
use App\Service\AvatarUpdater;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
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
            new GoogleUserResolver($this->userRepository, $this->entityManager),
            $this->urlGenerator,
            new ApiTokenStore(false),
            new AvatarUpdater(new MockHttpClient(), $this->entityManager, new NullLogger()),
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
        $request = $this->request('');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Missing credentials');
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnZeroCredential(): void
    {
        $request = $this->request('0');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Missing credentials');
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsWithoutEmail(): void
    {
        $verifier = $this->createStub(GoogleIdTokenVerifierInterface::class);
        $verifier->method('verify')->willReturn(['sub' => 'gid-123', 'email_verified' => true]);
        $authenticator = new GoogleIdentityAuthenticator(
            $verifier,
            new GoogleUserResolver($this->userRepository, $this->entityManager),
            $this->urlGenerator,
            new ApiTokenStore(false),
            new AvatarUpdater(new MockHttpClient(), $this->entityManager, new NullLogger()),
        );
        $request = $this->request('token');

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token');
        $authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsWithoutCsrfCookie(): void
    {
        $request = new Request([], ['credential' => 'token', GoogleIdentityAuthenticator::CSRF_TOKEN => 'csrf']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid CSRF token');
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnCsrfMismatch(): void
    {
        $request = $this->request('token');
        $request->request->set(GoogleIdentityAuthenticator::CSRF_TOKEN, 'other');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid CSRF token');
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnUnverifiedEmail(): void
    {
        $verifier = $this->createStub(GoogleIdTokenVerifierInterface::class);
        $verifier->method('verify')->willReturn(['sub' => 'gid-123', 'email' => 'victim@example.com', 'email_verified' => false]);
        $authenticator = new GoogleIdentityAuthenticator(
            $verifier,
            new GoogleUserResolver($this->userRepository, $this->entityManager),
            $this->urlGenerator,
            new ApiTokenStore(false),
            new AvatarUpdater(new MockHttpClient(), $this->entityManager, new NullLogger()),
        );

        $this->userRepository->expects(self::never())->method('findOneBy');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Email address is not verified');
        $authenticator->authenticate($this->request('token'));
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

    /** A sign-in post with Google's matching CSRF cookie and field. */
    private function request(string $credential): Request
    {
        $request = new Request(
            [],
            ['credential' => $credential, GoogleIdentityAuthenticator::CSRF_TOKEN => 'csrf'],
            [],
            [GoogleIdentityAuthenticator::CSRF_TOKEN => 'csrf'],
        );
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
