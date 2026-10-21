<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\ApiResource\CustomMedals;
use App\Repository\UserRepository;
use App\Security\FakeGoogleIdTokenVerifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomMedalsApiTest extends WebTestCase
{
    /** An agent: may save. */
    private const string GOOGLE_ID = 'gid-medals-agent';

    /** A user without the Agent role (newly registered): no custom medals at all. */
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
            ->createQuery("UPDATE App\\Entity\\User u SET u.googleId = NULL, u.customMedals = '{}'")
            ->execute();

        parent::tearDown();
    }

    public function testRequiresToken(): void
    {
        $this->request(Request::METHOD_GET, null);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->request(Request::METHOD_PUT, null, self::MEDALS);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testRequiresTheAgentRole(): void
    {
        $this->request(Request::METHOD_PUT, self::USER_GOOGLE_ID, self::MEDALS);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(CustomMedals::AGENT_ROLE_NEEDED, $this->responseJson()['detail']);

        $this->request(Request::METHOD_GET, self::USER_GOOGLE_ID);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(CustomMedals::AGENT_ROLE_NEEDED, $this->responseJson()['detail']);
    }

    public function testNoMedalsYet(): void
    {
        $this->request(Request::METHOD_GET);

        self::assertResponseIsSuccessful();
        $json = $this->responseJson();
        self::assertSame([], $json['anomaly']);
        self::assertSame([], $json['annual']);
        self::assertSame([], $json['event']);
    }

    public function testPutReplacesTheMedals(): void
    {
        $this->request(Request::METHOD_PUT, self::GOOGLE_ID, self::MEDALS);
        self::assertResponseIsSuccessful();
        self::assertSame(self::MEDALS['anomaly'], $this->responseJson()['anomaly']);

        $this->request(Request::METHOD_PUT, self::GOOGLE_ID, ['anomaly' => ['orion' => 'gold']]);
        self::assertResponseIsSuccessful();

        $user = $this->userRepository()->findOneBy(['identifier' => 'agent']);
        self::assertNotNull($user);
        $this->entityManager()->refresh($user);
        self::assertSame(['anomaly' => ['orion' => 'gold']], $user->getCustomMedals());

        $this->request(Request::METHOD_GET);
        $json = $this->responseJson();
        self::assertSame(['orion' => 'gold'], $json['anomaly']);
        self::assertSame([], $json['annual']);
    }

    public function testStoredAs4eWebsite(): void
    {
        $this->request(Request::METHOD_PUT, self::GOOGLE_ID, self::MEDALS);

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
        yield 'bad medal key' => [['anomaly' => ['Orion!' => 'gold']], 'anomaly'];
        yield 'bad tier' => [['event' => ['orion' => 'Gold Tier']], 'event[orion]'];
        yield 'annual level too high' => [['annual' => ['voyager' => 6]], 'annual[voyager]'];
        yield 'annual level as tier' => [['annual' => ['voyager' => 'black']], 'annual[voyager]'];
    }

    /**
     * @param array<mixed> $payload
     */
    #[DataProvider('invalidPayloadProvider')]
    public function testRejectsInvalidPayload(array $payload, string $propertyPath): void
    {
        $this->request(Request::METHOD_PUT, self::GOOGLE_ID, $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        /** @var list<array{propertyPath: string}> $violations */
        $violations = $this->responseJson()['violations'];
        self::assertContains($propertyPath, \array_column($violations, 'propertyPath'));
    }

    /**
     * @param array<mixed>|null $payload
     */
    private function request(string $method, ?string $googleId = self::GOOGLE_ID, ?array $payload = null): void
    {
        $server = ['HTTP_ACCEPT' => 'application/ld+json'];
        if (null !== $googleId) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.FakeGoogleIdTokenVerifier::token($googleId, $googleId.'@example.com');
        }

        if (null !== $payload) {
            $server['CONTENT_TYPE'] = 'application/ld+json';
        }

        $this->client->request(
            $method,
            '/api/me/custom-medals',
            server: $server,
            content: null === $payload ? null : (string) \json_encode($payload),
        );
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
