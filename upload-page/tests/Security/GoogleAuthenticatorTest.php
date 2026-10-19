<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\GoogleAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

#[AllowMockObjectsWithoutExpectations]
class GoogleAuthenticatorTest extends TestCase
{
    private GoogleAuthenticator $authenticator;

    private ClientRegistry&MockObject $clientRegistry;

    private UserRepository&MockObject $userRepository;

    private EntityManagerInterface&MockObject $entityManager;

    private UrlGeneratorInterface&MockObject $urlGenerator;

    protected function setUp(): void
    {
        $this->clientRegistry = $this->createMock(ClientRegistry::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->authenticator = new GoogleAuthenticator(
            $this->clientRegistry,
            $this->entityManager,
            $this->userRepository,
            $this->urlGenerator,
        );
    }

    public function testSupportsGoogleCheckPath(): void
    {
        $request = Request::create('/connect/google/check');
        self::assertTrue($this->authenticator->supports($request));
    }

    public function testDoesNotSupportOtherPaths(): void
    {
        self::assertFalse($this->authenticator->supports(Request::create('/')));
        self::assertFalse($this->authenticator->supports(Request::create('/connect/google/verify')));
    }

    public function testAuthenticateReturnsPassport(): void
    {
        $accessToken = $this->createStub(AccessToken::class);

        $googleUser = $this->createStub(GoogleUser::class);
        $googleUser->method('getId')->willReturn('google-789');

        $oauthClient = $this->createMock(OAuth2ClientInterface::class);
        $oauthClient->expects(self::once())->method('getAccessToken')->willReturn($accessToken);
        $oauthClient->expects(self::once())->method('fetchUserFromToken')->with($accessToken)->willReturn($googleUser);

        $this->clientRegistry->expects(self::exactly(2))->method('getClient')->with('google')->willReturn($oauthClient);

        $existingUser = new User()->setIdentifier('test@example.com')->setGoogleId('google-789');
        $this->userRepository->method('findOneBy')->willReturn($existingUser);

        $request = Request::create('/connect/google/check');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $passport = $this->authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    public function testGetUserReturnsExistingUserByGoogleId(): void
    {
        $existingUser = new User()->setIdentifier('test@example.com')->setGoogleId('google-123');

        $this->userRepository->expects(self::once())->method('findOneBy')
            ->with(['googleId' => 'google-123'])
            ->willReturn($existingUser);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $googleUser = $this->createStub(GoogleUser::class);
        $googleUser->method('getId')->willReturn('google-123');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, $googleUser);

        self::assertSame($existingUser, $result);
    }

    public function testGetUserLinksExistingAccountByEmail(): void
    {
        $existingUser = new User()->setIdentifier('shared@example.com');

        $this->userRepository->method('findOneBy')
            ->willReturnMap([
                [['googleId' => 'google-456'], null],
                [['identifier' => 'shared@example.com'], $existingUser],
            ]);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $googleUser = $this->createStub(GoogleUser::class);
        $googleUser->method('getId')->willReturn('google-456');
        $googleUser->method('getEmail')->willReturn('shared@example.com');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, $googleUser);

        self::assertSame($existingUser, $result);
        self::assertSame('google-456', $result->getGoogleId());
    }

    public function testGetUserCreatesNewUserWhenNotFound(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $googleUser = $this->createStub(GoogleUser::class);
        $googleUser->method('getId')->willReturn('google-456');
        $googleUser->method('getEmail')->willReturn('new@example.com');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, $googleUser);

        self::assertSame('new@example.com', $result->getIdentifier());
        self::assertSame('google-456', $result->getGoogleId());
    }

    public function testOnAuthenticationSuccessRedirectsToTargetPath(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.main.target_path', '/admin');

        $request = new Request();
        $request->setSession($session);

        $response = $this->authenticator->onAuthenticationSuccess(
            $request,
            $this->createStub(TokenInterface::class),
            'main'
        );

        self::assertSame('/admin', $response->getTargetUrl());
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

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $response = $this->authenticator->onAuthenticationFailure(
            $request,
            new AuthenticationException('Bad credentials.')
        );

        self::assertSame('/login', $response->getTargetUrl());
        self::assertNotEmpty($session->getFlashBag()->get('danger'));
    }
}
