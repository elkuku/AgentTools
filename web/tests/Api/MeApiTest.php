<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\ApiResource\Me;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\FakeGoogleIdTokenVerifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PATCH /api/me: the agent name. GET /api/me is also tested in AgentStatApiTest.
 */
class MeApiTest extends WebTestCase
{
    /** An agent without an agent name yet. */
    private const string GOOGLE_ID = 'gid-me-agent';

    /** A user without the Agent role (newly registered). */
    private const string USER_GOOGLE_ID = 'gid-me-user';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        foreach (['agent' => self::GOOGLE_ID, 'user' => self::USER_GOOGLE_ID] as $identifier => $googleId) {
            $this->user($identifier)->setGoogleId($googleId);
        }

        // Another agent's name
        $this->user('admin')->setAgentName('AdminAgent');

        $this->entityManager()->flush();
    }

    protected function tearDown(): void
    {
        $this->entityManager()
            ->createQuery('UPDATE App\Entity\User u SET u.googleId = NULL, u.agentName = NULL')
            ->execute();

        parent::tearDown();
    }

    public function testRequiresToken(): void
    {
        $this->request(Request::METHOD_PATCH, null, ['agentName' => 'TestAgent']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testRequiresTheAgentRole(): void
    {
        $this->request(Request::METHOD_PATCH, self::USER_GOOGLE_ID, ['agentName' => 'TestAgent']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(Me::AGENT_ROLE_NEEDED, $this->responseJson()['detail']);
        self::assertNull($this->user('user')->getAgentName());
    }

    public function testOnlyTheAgentNameIsWritable(): void
    {
        $this->request(Request::METHOD_PATCH, payload: ['agentName' => 'TestAgent', 'role' => 'ROLE_ADMIN', 'identifier' => 'someone']);

        self::assertResponseIsSuccessful();
        $this->entityManager()->clear();
        $agent = $this->user('agent');
        self::assertSame('TestAgent', $agent->getAgentName());
        self::assertSame('ROLE_AGENT', $agent->getRole()->value);
    }

    public function testSetsAndShowsTheAgentName(): void
    {
        $this->request(Request::METHOD_GET);
        self::assertResponseIsSuccessful();
        self::assertNull($this->responseJson()['agentName']);

        $this->request(Request::METHOD_PATCH, payload: ['agentName' => ' TestAgent ']);

        self::assertResponseIsSuccessful();
        $json = $this->responseJson();
        self::assertSame('TestAgent', $json['agentName']);
        self::assertSame('agent', $json['identifier']);
        self::assertSame('ROLE_AGENT', $json['role']);
        $this->entityManager()->clear();
        self::assertSame('TestAgent', $this->user('agent')->getAgentName());

        // Changed again, e.g. a typo
        $this->request(Request::METHOD_PATCH, payload: ['agentName' => 'Test_Agent2']);
        self::assertResponseIsSuccessful();
        $this->request(Request::METHOD_GET);
        self::assertSame('Test_Agent2', $this->responseJson()['agentName']);
    }

    public function testNullKeepsTheAgentName(): void
    {
        $this->request(Request::METHOD_PATCH, payload: ['agentName' => 'TestAgent']);
        $this->request(Request::METHOD_PATCH, payload: ['agentName' => null]);

        self::assertResponseIsSuccessful();
        self::assertSame('TestAgent', $this->responseJson()['agentName']);
    }

    public function testRejectsAnotherAgentsName(): void
    {
        $this->request(Request::METHOD_PATCH, payload: ['agentName' => 'adminagent']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        /** @var list<array{propertyPath: string, message: string}> $violations */
        $violations = $this->responseJson()['violations'];
        self::assertSame([['propertyPath' => 'agentName', 'message' => User::AGENT_NAME_TAKEN]], \array_map(
            static fn (array $violation): array => ['propertyPath' => $violation['propertyPath'], 'message' => $violation['message']],
            $violations,
        ));
        $this->entityManager()->clear();
        self::assertNull($this->user('agent')->getAgentName());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidNameProvider(): iterable
    {
        yield 'blank' => [' '];
        yield 'space inside' => ['Test Agent'];
        yield 'too long' => [\str_repeat('a', 51)];
        yield 'not a string' => [42];
    }

    #[DataProvider('invalidNameProvider')]
    public function testRejectsInvalidNames(mixed $agentName): void
    {
        $this->request(Request::METHOD_PATCH, payload: ['agentName' => $agentName]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        /** @var list<array{propertyPath: string}> $violations */
        $violations = $this->responseJson()['violations'];
        self::assertContains('agentName', \array_column($violations, 'propertyPath'));
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
            $server['CONTENT_TYPE'] = 'application/merge-patch+json';
        }

        $this->client->request(
            $method,
            '/api/me',
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

    private function user(string $identifier): User
    {
        $repository = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);
        $user = $repository->findOneBy(['identifier' => $identifier]);
        self::assertNotNull($user);

        return $user;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
