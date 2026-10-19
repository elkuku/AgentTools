<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Enum\UploadSource;
use App\Repository\UploadRepository;
use App\Repository\UserRepository;
use App\Tests\Security\FakeGoogleIdTokenVerifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadCreateTest extends WebTestCase
{
    private const string GOOGLE_ID = 'gid-api-user';

    private const string LINK_EMAIL = 'link-me@example.com';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $user = $this->userRepository()->findOneBy(['identifier' => 'user']);
        self::assertNotNull($user);
        $user->setGoogleId(self::GOOGLE_ID);
        $this->entityManager()->flush();
    }

    protected function tearDown(): void
    {
        $em = $this->entityManager();
        $em->createQuery('DELETE FROM App\Entity\Upload')->execute();
        $em->createQuery('UPDATE App\Entity\User u SET u.googleId = NULL')->execute();
        $em->createQuery('DELETE FROM App\Entity\User u WHERE u.identifier = :email')
            ->setParameter('email', self::LINK_EMAIL)
            ->execute();

        parent::tearDown();
    }

    public function testRequiresToken(): void
    {
        $this->postJson(['title' => 'x', 'data' => ['a' => 1]]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(['error' => 'Authentication required.'], $this->responseJson());
    }

    public function testRejectsInvalidToken(): void
    {
        $this->postJson(['title' => 'x', 'data' => ['a' => 1]], 'not-a-google-token');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(['error' => 'Invalid ID token.'], $this->responseJson());
    }

    public function testRejectsUnverifiedEmail(): void
    {
        $this->postJson(
            ['title' => 'x', 'data' => ['a' => 1]],
            FakeGoogleIdTokenVerifier::token(self::GOOGLE_ID, 'user@example.com', false)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(['error' => 'Email address is not verified.'], $this->responseJson());
    }

    public function testRejectsUnknownUser(): void
    {
        $this->postJson(
            ['title' => 'x', 'data' => ['a' => 1]],
            FakeGoogleIdTokenVerifier::token('gid-nobody', 'nobody@example.com')
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(['error' => 'Unknown user.'], $this->responseJson());
        self::assertNull($this->userRepository()->findOneBy(['identifier' => 'nobody@example.com']));
    }

    public function testCreatesUploadForKnownGoogleId(): void
    {
        $data = ['temperature' => 21.5, 'tags' => ['a', 'b'], 'nested' => ['ok' => true]];

        $this->postJson(
            ['title' => 'From the app', 'data' => $data],
            FakeGoogleIdTokenVerifier::token(self::GOOGLE_ID, 'user@example.com')
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $json = $this->responseJson();
        self::assertIsInt($json['id']);
        self::assertIsString($json['createdAt']);
        self::assertResponseHeaderSame('Location', 'http://localhost/uploads/'.$json['id']);

        $upload = $this->uploadRepository()->find($json['id']);
        self::assertNotNull($upload);
        self::assertSame('From the app', $upload->getTitle());
        self::assertSame($data, $upload->getData());
        self::assertSame(UploadSource::APP, $upload->getSource());
        self::assertSame('user', $upload->getOwner()->getIdentifier());
    }

    public function testLinksExistingUserByEmail(): void
    {
        $em = $this->entityManager();
        $em->persist(new User()->setIdentifier(self::LINK_EMAIL));
        $em->flush();

        $this->postJson(
            ['title' => 'Linked', 'data' => ['a' => 1]],
            FakeGoogleIdTokenVerifier::token('gid-linked', self::LINK_EMAIL)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $em->clear();
        $user = $this->userRepository()->findOneBy(['identifier' => self::LINK_EMAIL]);
        self::assertNotNull($user);
        self::assertSame('gid-linked', $user->getGoogleId());
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'missing title' => [['data' => ['a' => 1]]];
        yield 'blank title' => [['title' => '  ', 'data' => ['a' => 1]]];
        yield 'too long title' => [['title' => \str_repeat('x', 256), 'data' => ['a' => 1]]];
        yield 'missing data' => [['title' => 'x']];
        yield 'empty data' => [['title' => 'x', 'data' => []]];
        yield 'list data' => [['title' => 'x', 'data' => [1, 2, 3]]];
        yield 'too large data' => [['title' => 'x', 'data' => ['blob' => \str_repeat('x', 70000)]]];
    }

    /**
     * @param array<mixed> $payload
     */
    #[DataProvider('invalidPayloadProvider')]
    public function testRejectsInvalidPayload(array $payload): void
    {
        $this->postJson($payload, FakeGoogleIdTokenVerifier::token(self::GOOGLE_ID, 'user@example.com'));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([], $this->uploadRepository()->findAll());
    }

    public function testRejectsMalformedJson(): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/api/uploads',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.FakeGoogleIdTokenVerifier::token(self::GOOGLE_ID, 'user@example.com'),
            ],
            content: '{"title": "x", "data": {',
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRejectsNonJsonContentType(): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/api/uploads',
            ['title' => 'x', 'data' => ['a' => '1']],
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.FakeGoogleIdTokenVerifier::token(self::GOOGLE_ID, 'user@example.com'),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
    }

    /**
     * @param array<mixed> $payload
     */
    private function postJson(array $payload, ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request(
            Request::METHOD_POST,
            '/api/uploads',
            server: $server,
            content: (string) \json_encode($payload),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseJson(): array
    {
        /** @var array<string, mixed> $json */
        $json = \json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $json;
    }

    private function userRepository(): UserRepository
    {
        $repository = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        return $repository;
    }

    private function uploadRepository(): UploadRepository
    {
        $repository = static::getContainer()->get(UploadRepository::class);
        self::assertInstanceOf(UploadRepository::class, $repository);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
