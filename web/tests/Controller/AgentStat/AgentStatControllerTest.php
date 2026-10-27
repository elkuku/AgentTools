<?php

declare(strict_types=1);

namespace App\Tests\Controller\AgentStat;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentStatControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        $this->entityManager()->createQuery('DELETE FROM App\Entity\AgentStat')->execute();

        parent::tearDown();
    }

    public function testPagesRequireAuthentication(): void
    {
        $agentStat = $this->createAgentStat($this->getUserByIdentifier('agent'), 'Mine');

        foreach (['/agent-stats', '/agent-stats/'.$agentStat->id] as $path) {
            $this->client->request(Request::METHOD_GET, $path);
            self::assertResponseRedirects();
        }
    }

    public function testIndexListsOnlyOwnStats(): void
    {
        $this->createAgentStat($this->getUserByIdentifier('agent'), 'MyAgent');
        $this->createAgentStat($this->getUserByIdentifier('admin'), 'OtherAgent');

        $this->client->loginUser($this->getUserByIdentifier('agent'));
        $this->client->request(Request::METHOD_GET, '/agent-stats');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#agent-stats', 'MyAgent');
        // The imports are on the timeline, each one links to its stats
        self::assertSelectorTextContains('#timeline-entries .timeline-upload', '2026-10-19 15:35');
        self::assertSelectorTextContains('#timeline-entries .timeline-upload', '243,517,130 AP');
        self::assertSelectorExists('#timeline-entries .timeline-upload a[href^="/agent-stats/"]');
        self::assertSelectorTextNotContains('body', 'OtherAgent');
    }

    public function testShowRendersStats(): void
    {
        $agentStat = $this->createAgentStat($this->getUserByIdentifier('agent'), 'MyAgent');

        $this->client->loginUser($this->getUserByIdentifier('agent'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/'.$agentStat->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#agent-stat-title', 'MyAgent – 2026-10-19 15:35:56');
        self::assertSelectorTextContains('table', 'mind_controller');
        self::assertSelectorTextContains('table', '35,236');
    }

    public function testShowDeniesOtherUsersStats(): void
    {
        $agentStat = $this->createAgentStat($this->getUserByIdentifier('admin'), 'OtherAgent');

        $this->client->loginUser($this->getUserByIdentifier('agent'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/'.$agentStat->id);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRegisteredUsersSeeTheirStats(): void
    {
        $user = $this->getUserByIdentifier('user');
        $agentStat = $this->createAgentStat($user, 'Mine');

        $this->client->loginUser($user);
        foreach (['/agent-stats', '/agent-stats/'.$agentStat->id] as $path) {
            $this->client->request(Request::METHOD_GET, $path);
            self::assertResponseIsSuccessful();
        }
    }

    private function createAgentStat(User $owner, string $nickname): AgentStat
    {
        $agentStat = new AgentStat($owner, new AgentStatInput(
            datetime: '2026-10-19 15:35:56',
            nickname: $nickname,
            faction: 'Enlightened',
            ap: 243517130,
            level: 16,
            mindController: 35236,
        ));
        $this->entityManager()->persist($agentStat);
        $this->entityManager()->flush();

        return $agentStat;
    }

    private function getUserByIdentifier(string $identifier): User
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
