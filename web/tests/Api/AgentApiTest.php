<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\User;
use App\Enum\ProfilePart;
use App\Repository\UserRepository;
use App\Security\FakeGoogleIdTokenVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/agents and /api/agents/{agentName}: the app's list of agents, as web's /agents.
 */
class AgentApiTest extends WebTestCase
{
    /** The one looking. */
    private const string GOOGLE_ID = 'gid-agents-admin';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->user('admin')->setGoogleId(self::GOOGLE_ID);
        $this->user('agent')->setAgentName('TestAgent');
        $this->user('user')->setAgentName('PrivateAgent');
        $this->entityManager()->flush();
    }

    protected function tearDown(): void
    {
        $em = $this->entityManager();
        $em->createQuery('DELETE FROM App\Entity\AgentStat')->execute();
        $em->createQuery("UPDATE App\\Entity\\User u SET u.googleId = NULL, u.agentName = NULL, u.avatar = NULL, u.avatarSource = NULL, u.visibleParts = '[]', u.visibilityChosenAt = NULL, u.customMedals = '{}'")
            ->execute();

        parent::tearDown();
    }

    public function testRequiresToken(): void
    {
        $this->get('/api/agents', null);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListsOnlyAgentsWhoShowTheirProfile(): void
    {
        $this->show('agent', [ProfilePart::AGENT, ProfilePart::AVATAR]);
        $this->user('agent')->setAvatar('data:image/jpeg;base64,cGljdHVyZQ==', 'https://lh3.googleusercontent.com/a');
        $this->show('user', [ProfilePart::LATEST_STATS]);
        $this->createAgentStat($this->user('agent'), '2026-10-19 15:35:56', 16);

        $json = $this->get('/api/agents');

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $members */
        $members = $json['member'];
        self::assertCount(1, $members);
        self::assertSame('/api/agents/TestAgent', $members[0]['@id']);
        self::assertSame('TestAgent', $members[0]['agentName']);
        self::assertSame('Enlightened', $members[0]['faction']);
        self::assertSame(16, $members[0]['level']);
        self::assertSame('data:image/jpeg;base64,cGljdHVyZQ==', $members[0]['avatar']);
        // The details are the agent's
        self::assertArrayNotHasKey('latestStats', $members[0]);
    }

    public function testShowsOnlyTheVisibleParts(): void
    {
        $this->show('agent', [ProfilePart::AGENT, ProfilePart::LATEST_STATS]);
        $this->createAgentStat($this->user('agent'), '2026-10-19 15:35:56', 15);
        $this->createAgentStat($this->user('agent'), '2026-10-20 15:35:56', 16);

        // Agent names ignore case
        $json = $this->get('/api/agents/testagent');

        self::assertResponseIsSuccessful();
        self::assertSame('TestAgent', $json['agentName']);
        /** @var array<string, mixed> $latest */
        $latest = $json['latestStats'];
        self::assertSame('2026-10-20 15:35:56', $latest['datetime']);
        self::assertSame('Enlightened', $latest['faction']);
        self::assertSame(243517130, $latest['ap']);
        self::assertArrayNotHasKey('id', $latest);
        self::assertNull($json['history'] ?? null);
        self::assertNull($json['customMedals'] ?? null);
        self::assertNull($json['avatar'] ?? null);
    }

    public function testShowsTheHistoryAndTheCustomMedals(): void
    {
        $agent = $this->user('agent');
        $agent->setCustomMedals(['anomaly' => ['orion' => 'platinum'], 'annual' => ['voyager' => 5]]);
        $this->show('agent', [ProfilePart::AGENT, ProfilePart::STATS_HISTORY, ProfilePart::CUSTOM_MEDALS]);
        $this->createAgentStat($agent, '2026-10-19 15:35:56', 15);
        $this->createAgentStat($agent, '2026-10-20 15:35:56', 16);

        $json = $this->get('/api/agents/TestAgent');

        self::assertNull($json['latestStats'] ?? null);
        /** @var list<array<string, mixed>> $history */
        $history = $json['history'];
        self::assertSame(['2026-10-20 15:35:56', '2026-10-19 15:35:56'], \array_column($history, 'datetime'));
        /** @var array<string, mixed> $medals */
        $medals = $json['customMedals'];
        self::assertSame(['orion' => 'platinum'], $medals['anomaly']);
        self::assertSame(['voyager' => 5], $medals['annual']);
        self::assertSame([], $medals['event']);
    }

    public function testPrivateAgentsAreNotFound(): void
    {
        $this->show('user', [ProfilePart::LATEST_STATS]);

        foreach (['/api/agents/PrivateAgent', '/api/agents/NoSuchAgent'] as $path) {
            $this->get($path);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @param list<ProfilePart> $parts
     */
    private function show(string $identifier, array $parts): void
    {
        $this->user($identifier)->chooseVisibleParts($parts, new \DateTimeImmutable());
        $this->entityManager()->flush();
    }

    private function createAgentStat(User $owner, string $datetime, int $level): void
    {
        $this->entityManager()->persist(new AgentStat($owner, new AgentStatInput(
            datetime: $datetime,
            nickname: (string) $owner->getAgentName(),
            faction: 'Enlightened',
            ap: 243517130,
            level: $level,
        )));
        $this->entityManager()->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $uri, ?string $googleId = self::GOOGLE_ID): array
    {
        $server = ['HTTP_ACCEPT' => 'application/ld+json'];
        if (null !== $googleId) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.FakeGoogleIdTokenVerifier::token($googleId, $googleId.'@example.com');
        }

        $this->client->request(Request::METHOD_GET, $uri, server: $server);

        /** @var array<string, mixed> $json */
        $json = \json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];

        return $json;
    }

    private function user(string $identifier): User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['identifier' => $identifier]);
        self::assertNotNull($user);

        return $user;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
