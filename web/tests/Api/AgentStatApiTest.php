<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Repository\AgentStatRepository;
use App\Repository\UserRepository;
use App\Security\FakeGoogleIdTokenVerifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentStatApiTest extends WebTestCase
{
    private const string GOOGLE_ID = 'gid-stats-user';

    private const string ADMIN_GOOGLE_ID = 'gid-stats-admin';

    /**
     * What the app sends for Ingress all-time stats.
     */
    private const array STATS = [
        'datetime' => '2026-10-19 15:35:56',
        'nickname' => 'TestAgent',
        'faction' => 'Enlightened',
        'level' => 16,
        'ap' => 243517130,
        'explorer' => 27659,
        'mind_controller' => 35236,
        'nl1331_meetups' => 7,
        'scout_controller' => 6,
        'months_subscribed' => 69,
        'epoch' => 274,
        'reclaimer' => 4710,
    ];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        foreach (['user' => self::GOOGLE_ID, 'admin' => self::ADMIN_GOOGLE_ID] as $identifier => $googleId) {
            $user = $this->userRepository()->findOneBy(['identifier' => $identifier]);
            self::assertNotNull($user);
            $user->setGoogleId($googleId);
        }

        $this->entityManager()->flush();
    }

    protected function tearDown(): void
    {
        $em = $this->entityManager();
        $em->createQuery('DELETE FROM App\Entity\AgentStat')->execute();
        $em->createQuery('UPDATE App\Entity\User u SET u.googleId = NULL')->execute();

        parent::tearDown();
    }

    public function testRequiresToken(): void
    {
        $this->postJson(self::STATS, null);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('Authentication required.', $this->responseJson()['detail']);
        self::assertSame([], $this->agentStatRepository()->findAll());
    }

    public function testCreatesAgentStat(): void
    {
        $this->postJson(self::STATS + ['xm_collected' => 1, 'Current AP' => 2]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $json = $this->responseJson();
        self::assertIsInt($json['id']);
        self::assertSame('/api/agent-stats/'.$json['id'], $json['@id']);
        self::assertSame('AgentStat', $json['@type']);
        self::assertIsString($json['createdAt']);
        self::assertSame('2026-10-19 15:35:56', $json['datetime']);
        self::assertSame(35236, $json['mind_controller']);
        self::assertArrayNotHasKey('owner', $json);
        self::assertArrayNotHasKey('stats', $json);
        self::assertResponseHeaderSame('Location', '/api/agent-stats/'.$json['id']);

        $stat = $this->agentStatRepository()->find($json['id']);
        self::assertNotNull($stat);
        self::assertSame('user', $stat->owner->getIdentifier());
        self::assertSame('2026-10-19 15:35:56', $stat->datetime->format('Y-m-d H:i:s'));
        self::assertSame('TestAgent', $stat->nickname);
        self::assertSame('Enlightened', $stat->faction);
        self::assertSame(243517130, $stat->ap);
        self::assertSame(16, $stat->level);
        self::assertSame(35236, $stat->mindController);
        self::assertSame(7, $stat->nl1331Meetups);
        self::assertSame(6, $stat->scoutController);
        self::assertSame(69, $stat->monthsSubscribed);
        self::assertSame(274, $stat->epoch);
        self::assertSame(4710, $stat->reclaimer);
        self::assertNull($stat->recon);
    }

    public function testRejectsSameStatsTwice(): void
    {
        $this->postJson(self::STATS);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->postJson(['ap' => 243517131] + self::STATS);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('Stat entry already added!', $this->responseJson()['detail']);
        self::assertCount(1, $this->agentStatRepository()->findAll());
    }

    public function testListsOwnStatsNewestFirst(): void
    {
        $this->postJson(['datetime' => '2026-10-01 10:00:00', 'ap' => 1000] + self::STATS);
        $this->postJson(self::STATS);
        $this->postJson(self::STATS, self::ADMIN_GOOGLE_ID);

        $this->get('/api/agent-stats');

        self::assertResponseIsSuccessful();
        $json = $this->responseJson();
        self::assertSame('Collection', $json['@type']);
        self::assertSame(2, $json['totalItems']);
        /** @var list<array<string, mixed>> $list */
        $list = $json['member'];
        self::assertCount(2, $list);
        self::assertSame('2026-10-19 15:35:56', $list[0]['datetime']);
        self::assertSame('TestAgent', $list[0]['nickname']);
        self::assertSame(243517130, $list[0]['ap']);
        self::assertSame(35236, $list[0]['mind_controller']);
        self::assertNull($list[0]['recon']);
        self::assertSame(1000, $list[1]['ap']);
    }

    public function testListLinksTheNextPage(): void
    {
        for ($minute = 0; $minute <= 30; ++$minute) {
            $this->postJson(['datetime' => \sprintf('2026-10-19 10:%02d:00', $minute)] + self::STATS);
        }

        $this->get('/api/agent-stats');

        $json = $this->responseJson();
        self::assertSame(31, $json['totalItems']);
        self::assertIsArray($json['member']);
        self::assertCount(30, $json['member']);
        self::assertIsArray($json['view']);
        self::assertSame('/api/agent-stats?page=2', $json['view']['next']);

        $this->get('/api/agent-stats?page=2');

        $json = $this->responseJson();
        self::assertIsArray($json['member']);
        self::assertCount(1, $json['member']);
        self::assertIsArray($json['view']);
        self::assertArrayNotHasKey('next', $json['view']);
    }

    public function testListRequiresToken(): void
    {
        $this->get('/api/agent-stats', null);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testShowsOnlyOwnStat(): void
    {
        $this->postJson(self::STATS);
        $iri = $this->responseJson()['@id'];
        self::assertIsString($iri);

        $this->get($iri);
        self::assertResponseIsSuccessful();
        self::assertSame(243517130, $this->responseJson()['ap']);

        $this->get($iri, self::ADMIN_GOOGLE_ID);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testShowsCurrentUser(): void
    {
        $this->get('/api/me');

        self::assertResponseIsSuccessful();
        $json = $this->responseJson();
        self::assertSame('/api/me', $json['@id']);
        self::assertSame('user', $json['identifier']);
        self::assertSame('ROLE_USER', $json['role']);
        self::assertIsInt($json['id']);
    }

    public function testCurrentUserRequiresToken(): void
    {
        $this->get('/api/me', null);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testDocsArePublic(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/docs.jsonopenapi');

        self::assertResponseIsSuccessful();
        $json = $this->responseJson();
        self::assertArrayHasKey('/api/agent-stats', $json['paths']);
        self::assertIsArray($json['components']['securitySchemes']['GoogleIdToken']);
        self::assertSame('bearer', $json['components']['securitySchemes']['GoogleIdToken']['scheme']);
        self::assertSame([['GoogleIdToken' => []]], $json['security']);
        self::assertIsArray($json['info']);
        self::assertIsString($json['info']['description']);
        self::assertStringNotContainsString('<', $json['info']['description']);
        self::assertContains(
            ['name' => 'AgentStat', 'description' => "Ingress all-time agent stats, with the column names of 4e-website's agent_stat table. Lists only your own, newest first."],
            $json['tags'],
        );
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        $without = static fn (string $key): array => \array_diff_key(self::STATS, [$key => true]);

        yield 'missing datetime' => [$without('datetime'), 'datetime'];
        yield 'bad datetime' => [['datetime' => '19.10.2026 15:35'] + self::STATS, 'datetime'];
        yield 'missing nickname' => [$without('nickname'), 'nickname'];
        yield 'blank faction' => [['faction' => ' '] + self::STATS, 'faction'];
        yield 'missing ap' => [$without('ap'), 'ap'];
        yield 'negative stat' => [['explorer' => -1] + self::STATS, 'explorer'];
        yield 'stat too large for INT' => [['explorer' => 2147483648] + self::STATS, 'explorer'];
        yield 'stat as text' => [['explorer' => 'lots'] + self::STATS, 'explorer'];
    }

    /**
     * @param array<mixed> $payload
     */
    #[DataProvider('invalidPayloadProvider')]
    public function testRejectsInvalidPayload(array $payload, string $propertyPath): void
    {
        $this->postJson($payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        /** @var list<array{propertyPath: string, message: string}> $violations */
        $violations = $this->responseJson()['violations'];
        self::assertContains($propertyPath, \array_column($violations, 'propertyPath'));
        self::assertSame([], $this->agentStatRepository()->findAll());
    }

    /**
     * @param array<mixed> $payload
     */
    private function postJson(array $payload, ?string $googleId = self::GOOGLE_ID): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/api/agent-stats',
            server: ['CONTENT_TYPE' => 'application/ld+json'] + $this->headers($googleId),
            content: (string) \json_encode($payload),
        );
    }

    private function get(string $uri, ?string $googleId = self::GOOGLE_ID): void
    {
        $this->client->request(Request::METHOD_GET, $uri, server: $this->headers($googleId));
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

    private function agentStatRepository(): AgentStatRepository
    {
        $repository = static::getContainer()->get(AgentStatRepository::class);
        self::assertInstanceOf(AgentStatRepository::class, $repository);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
