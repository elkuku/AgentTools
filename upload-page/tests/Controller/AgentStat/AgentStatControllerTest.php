<?php

declare(strict_types=1);

namespace App\Tests\Controller\AgentStat;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\User;
use App\Repository\AgentStatRepository;
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
        $agentStat = $this->createAgentStat($this->getUserByIdentifier('user'), 'Mine');

        foreach (['/agent-stats', '/agent-stats/new', '/agent-stats/'.$agentStat->id] as $path) {
            $this->client->request(Request::METHOD_GET, $path);
            self::assertResponseRedirects();
        }
    }

    public function testIndexListsOnlyOwnStats(): void
    {
        $this->createAgentStat($this->getUserByIdentifier('user'), 'MyAgent');
        $this->createAgentStat($this->getUserByIdentifier('admin'), 'OtherAgent');

        $this->client->loginUser($this->getUserByIdentifier('user'));
        $this->client->request(Request::METHOD_GET, '/agent-stats');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'MyAgent');
        self::assertSelectorTextContains('table', '243,517,130');
        self::assertSelectorTextNotContains('table', 'OtherAgent');
    }

    public function testShowRendersStats(): void
    {
        $agentStat = $this->createAgentStat($this->getUserByIdentifier('user'), 'MyAgent');

        $this->client->loginUser($this->getUserByIdentifier('user'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/'.$agentStat->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#agent-stat-title', 'MyAgent – 2026-10-19 15:35:56');
        self::assertSelectorTextContains('table', 'mind_controller');
        self::assertSelectorTextContains('table', '35,236');
    }

    public function testShowDeniesOtherUsersStats(): void
    {
        $agentStat = $this->createAgentStat($this->getUserByIdentifier('admin'), 'OtherAgent');

        $this->client->loginUser($this->getUserByIdentifier('user'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/'.$agentStat->id);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testImportFormCreatesAgentStat(): void
    {
        $this->client->loginUser($this->getUserByIdentifier('user'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Import', ['agent_stat_import[stats]' => $this->sample()]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('#agent-stat-title', 'TestAgent – 2026-10-19 15:35:56');
        self::assertSelectorTextContains('table', '243,517,130');

        $stats = $this->agentStatRepository()->findAll();
        self::assertCount(1, $stats);
        self::assertSame('user', $stats[0]->owner->getIdentifier());
        self::assertSame(35236, $stats[0]->mindController);
    }

    public function testImportFormRejectsInvalidStats(): void
    {
        $this->assertImportError('just some text', 'Paste the stats as copied from Ingress');
        $this->assertImportError(\str_replace('ALL TIME', 'WEEK', $this->sample()), 'Only all-time stats can be imported, not');
    }

    public function testImportFormRejectsDuplicate(): void
    {
        $this->createAgentStat($this->getUserByIdentifier('user'), 'TestAgent');

        $this->assertImportError($this->sample(), 'Stat entry already added!');
        self::assertCount(1, $this->agentStatRepository()->findAll());
    }

    private function assertImportError(string $stats, string $message): void
    {
        $this->client->loginUser($this->getUserByIdentifier('user'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/new');

        $this->client->submitForm('Import', ['agent_stat_import[stats]' => $stats]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('form[name="agent_stat_import"]', $message);
    }

    private function sample(): string
    {
        return (string) \file_get_contents(__DIR__.'/../../fixtures/ingress-stats-all-time.tsv');
    }

    private function agentStatRepository(): AgentStatRepository
    {
        $repository = static::getContainer()->get(AgentStatRepository::class);
        self::assertInstanceOf(AgentStatRepository::class, $repository);

        return $repository;
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
