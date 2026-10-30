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
use Symfony\Component\BrowserKit\Cookie;
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

        foreach (['/agent-stats', '/agent-stats/timeline', '/agent-stats/'.$agentStat->id] as $path) {
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
        self::assertSelectorTextNotContains('body', 'OtherAgent');
        // Not synced from the app yet
        self::assertSelectorExists('#bannergress #bannergress-none');
        // The timeline is on its own page: only the counts here
        self::assertSelectorNotExists('#timeline-entries');
        self::assertSelectorTextContains('#timeline-count-upload', '1');
        self::assertSelectorTextContains('#timeline-count-event', '0');
        self::assertSelectorExists('#timeline-summary h2 a[href="/agent-stats/timeline"]');
    }

    public function testTimelineListsOnlyOwnImports(): void
    {
        $this->createAgentStat($this->getUserByIdentifier('agent'), 'MyAgent');
        $this->createAgentStat($this->getUserByIdentifier('admin'), 'OtherAgent');

        $this->client->loginUser($this->getUserByIdentifier('agent'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline');

        self::assertResponseIsSuccessful();
        // Level and AP are only in the chart
        self::assertCount(1, $this->client->getCrawler()->filter('#timeline-entries .timeline-upload'));
        // The day in the page's language, without the time
        self::assertSelectorTextContains('#timeline-entries .timeline-upload', 'Oct 19, 2026');
        self::assertSelectorTextNotContains('#timeline-entries', '15:35');
        self::assertSelectorTextNotContains('#timeline-entries', 'AP');
        // No links in the list
        self::assertSelectorNotExists('#timeline-entries .timeline-upload a');
        self::assertSelectorExists('#timeline-chart-frame #timeline-chart');

        $this->client->getCookieJar()->set(new Cookie('locale', 'de'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline');
        self::assertSelectorTextContains('#timeline-entries .timeline-upload', '19.10.2026');
    }

    public function testTheDateFilterLoadsTheZoomedChart(): void
    {
        $this->createAgentStat($this->getUserByIdentifier('agent'), 'MyAgent');
        $this->client->loginUser($this->getUserByIdentifier('agent'));

        // Only the chart, for the days with the upload
        $crawler = $this->client->request(Request::METHOD_GET, '/agent-stats/timeline?from=2026-10-01&to=2026-10-31&chart=1');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#timeline-chart-frame #timeline-chart .chart-point'));
        self::assertSelectorNotExists('#timeline-entries');
        self::assertSelectorNotExists('nav');

        // Days without anything: an empty frame
        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline?from=2025-01-01&to=2025-12-31&chart=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#timeline-chart-frame');
        self::assertSelectorNotExists('#timeline-chart');

        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline?from=yesterday&chart=1');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
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
        foreach (['/agent-stats', '/agent-stats/timeline', '/agent-stats/'.$agentStat->id] as $path) {
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
