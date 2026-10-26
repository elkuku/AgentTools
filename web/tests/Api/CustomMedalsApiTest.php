<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Repository\UserRepository;
use App\Security\FakeGoogleIdTokenVerifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The custom medals in GET / PATCH /api/me.
 */
class CustomMedalsApiTest extends WebTestCase
{
    /** A user with medals in the tests. */
    private const string GOOGLE_ID = 'gid-medals-agent';

    /** Another user (newly registered). */
    private const string USER_GOOGLE_ID = 'gid-medals-user';

    /**
     * What the app sends: 4e-website's format.
     */
    private const array MEDALS = [
        'anomaly' => ['orion' => 'platinum', 'echo' => ''],
        'annual' => ['voyager' => 5],
        'event' => ['2026_summer_solstice' => 'silver'],
    ];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        foreach (['agent' => self::GOOGLE_ID, 'user' => self::USER_GOOGLE_ID] as $identifier => $googleId) {
            $user = $this->userRepository()->findOneBy(['identifier' => $identifier]);
            self::assertNotNull($user);
            $user->setGoogleId($googleId);
        }

        $this->entityManager()->flush();
    }

    protected function tearDown(): void
    {
        $this->entityManager()
            ->createQuery("UPDATE App\\Entity\\User u SET u.googleId = NULL, u.agentName = NULL, u.customMedals = '{}'")
            ->execute();

        parent::tearDown();
    }

    public function testRequiresToken(): void
    {
        $this->request(Request::METHOD_GET, null);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->request(Request::METHOD_PATCH, null, self::MEDALS);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testRegisteredUsersSaveMedals(): void
    {
        $this->request(Request::METHOD_PATCH, self::USER_GOOGLE_ID, self::MEDALS);
        self::assertResponseIsSuccessful();

        $this->request(Request::METHOD_GET, self::USER_GOOGLE_ID);
        self::assertResponseIsSuccessful();
        self::assertSame(self::MEDALS, \array_intersect_key($this->medalsJson(), self::MEDALS));
    }

    public function testNoMedalsYet(): void
    {
        $this->request(Request::METHOD_GET);

        self::assertResponseIsSuccessful();
        $json = $this->medalsJson();
        self::assertSame([], $json['anomaly']);
        self::assertSame([], $json['annual']);
        self::assertSame([], $json['event']);
    }

    public function testPatchReplacesTheMedals(): void
    {
        $this->request(Request::METHOD_PATCH, self::GOOGLE_ID, self::MEDALS);
        self::assertResponseIsSuccessful();
        self::assertSame(self::MEDALS['anomaly'], $this->medalsJson()['anomaly']);

        // A JSON merge patch: the groups sent are replaced, the others stay
        $this->request(Request::METHOD_PATCH, self::GOOGLE_ID, ['anomaly' => ['orion' => 'gold'], 'annual' => [], 'event' => []]);
        self::assertResponseIsSuccessful();

        $user = $this->userRepository()->findOneBy(['identifier' => 'agent']);
        self::assertNotNull($user);
        $this->entityManager()->refresh($user);
        self::assertSame(['anomaly' => ['orion' => 'gold']], $user->getCustomMedals());

        $this->request(Request::METHOD_GET);
        $json = $this->medalsJson();
        self::assertSame(['orion' => 'gold'], $json['anomaly']);
        self::assertSame([], $json['annual']);
    }

    public function testPatchKeepsTheGroupsNotSent(): void
    {
        $this->request(Request::METHOD_PATCH, self::GOOGLE_ID, self::MEDALS);

        $this->request(Request::METHOD_PATCH, self::GOOGLE_ID, ['anomaly' => ['orion' => 'gold']]);

        self::assertResponseIsSuccessful();
        $json = $this->medalsJson();
        self::assertSame(['orion' => 'gold'], $json['anomaly']);
        self::assertSame(self::MEDALS['annual'], $json['annual']);
        self::assertSame(self::MEDALS['event'], $json['event']);
    }

    public function testPatchWithoutMedalsKeepsThem(): void
    {
        $this->request(Request::METHOD_PATCH, self::GOOGLE_ID, self::MEDALS);

        $this->client->request(
            Request::METHOD_PATCH,
            '/api/me',
            server: $this->headers(self::GOOGLE_ID) + ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: (string) \json_encode(['agentName' => 'TestAgent']),
        );

        self::assertResponseIsSuccessful();
        self::assertSame(self::MEDALS['annual'], $this->medalsJson()['annual']);
    }

    public function testStoredAs4eWebsite(): void
    {
        $this->request(Request::METHOD_PATCH, self::GOOGLE_ID, self::MEDALS);

        $user = $this->userRepository()->findOneBy(['identifier' => 'agent']);
        self::assertNotNull($user);
        $this->entityManager()->refresh($user);
        self::assertSame(self::MEDALS, $user->getCustomMedals());
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'bad medal key' => [['anomaly' => ['Orion!' => 'gold']], 'customMedals.anomaly'];
        yield 'bad tier' => [['event' => ['orion' => 'Gold Tier']], 'customMedals.event[orion]'];
        yield 'annual level too high' => [['annual' => ['voyager' => 6]], 'customMedals.annual[voyager]'];
        yield 'annual level as tier' => [['annual' => ['voyager' => 'black']], 'customMedals.annual[voyager]'];
    }

    /**
     * @param array<mixed> $payload
     */
    #[DataProvider('invalidPayloadProvider')]
    public function testRejectsInvalidPayload(array $payload, string $propertyPath): void
    {
        $this->request(Request::METHOD_PATCH, self::GOOGLE_ID, $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        /** @var list<array{propertyPath: string}> $violations */
        $violations = $this->responseJson()['violations'];
        self::assertContains($propertyPath, \array_column($violations, 'propertyPath'));
    }

    /**
     * @param array<mixed>|null $medals sent as customMedals of the user
     */
    private function request(string $method, ?string $googleId = self::GOOGLE_ID, ?array $medals = null): void
    {
        $server = $this->headers($googleId);
        if (null !== $medals) {
            $server['CONTENT_TYPE'] = 'application/merge-patch+json';
        }

        $this->client->request(
            $method,
            '/api/me',
            server: $server,
            content: null === $medals ? null : (string) \json_encode(['customMedals' => $medals]),
        );
    }

    /**
     * @return array<string, string>
     */
    private function headers(?string $googleId): array
    {
        $headers = ['HTTP_ACCEPT' => 'application/ld+json'];
        if (null !== $googleId) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.FakeGoogleIdTokenVerifier::token($googleId, $googleId.'@example.com');
        }

        return $headers;
    }

    /**
     * @return array<mixed> the customMedals of the user in the response
     */
    private function medalsJson(): array
    {
        $medals = $this->responseJson()['customMedals'];
        self::assertIsArray($medals);

        return $medals;
    }

    /**
     * @return array<mixed>
     */
    private function responseJson(): array
    {
        /** @var array<mixed> $json */
        $json = \json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $json;
    }

    private function userRepository(): UserRepository
    {
        $repository = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
