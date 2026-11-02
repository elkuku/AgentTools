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

/**
 * The comparison of two uploads on /agent-stats/timeline: the days to pick from, and what changed
 * between two uploads (compare=1, loaded by timeline_filter_controller.js).
 */
class TimelineCompareTest extends WebTestCase
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

    public function testTheTimelineChoosesWhatToCompare(): void
    {
        $this->createAgentStat($this->user('agent'), '2026-09-01 10:00:00', ap: 1_000_000, explorer: 900);
        $this->createAgentStat($this->user('agent'), '2026-10-11 09:00:00', ap: 2_400_000, explorer: 2550);
        $this->createAgentStat($this->user('agent'), '2026-10-11 18:00:00', ap: 2_500_000, explorer: 2600, recursions: 1);
        $this->createAgentStat($this->user('admin'), '2026-10-12 10:00:00', ap: 9_000_000, explorer: 9000);

        $this->client->loginUser($this->user('agent'));
        $crawler = $this->client->request(Request::METHOD_GET, '/agent-stats/timeline');
        self::assertResponseIsSuccessful();

        // The list first; the comparison is loaded in the browser
        self::assertSelectorExists('#timeline-view-list[checked]');
        self::assertSelectorExists('#compare[hidden]');
        self::assertSelectorNotExists('#comparison');
        // The days to pick from: only the own uploads, with their times
        $days = (array) \json_decode((string) $crawler->filter('[data-controller="timeline-filter"]')->attr('data-timeline-filter-days-value'), true);
        self::assertSame(['2026-09-01', '2026-10-11'], \array_keys($days));
        self::assertSame(['09:00:00', '18:00:00'], \array_column((array) $days['2026-10-11'], 'time'));
        self::assertSelectorTextContains('#timeline-entries', 'Recursion: 1');
    }

    public function testComparesTwoUploads(): void
    {
        $first = $this->createAgentStat($this->user('agent'), '2026-09-01 10:00:00', ap: 1_000_000, explorer: 900);
        $morning = $this->createAgentStat($this->user('agent'), '2026-10-11 09:00:00', ap: 2_400_000, explorer: 2550);
        $evening = $this->createAgentStat($this->user('agent'), '2026-10-11 18:00:00', ap: 2_500_000, explorer: 2600, recursions: 1);
        $other = $this->createAgentStat($this->user('admin'), '2026-10-12 10:00:00', ap: 9_000_000, explorer: 9000);

        $this->client->loginUser($this->user('agent'));

        // Without a choice the first and the latest upload
        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline?compare=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#timeline-entries');
        self::assertSelectorTextContains('#comparison-days', '40 days');
        self::assertSelectorTextContains('.comparison-row[data-column="ap"]', '+ 1,500,000');
        // Explorer from bronze to gold, the recursion
        self::assertCount(1, $this->client->getCrawler()->filter('#comparison-medal-ups img'));
        self::assertSelectorTextContains('#comparison', 'Recursion: 1');
        // Only the gain, with the tier as the tooltip
        self::assertSelectorTextContains('.comparison-row[data-column="explorer"]', '+ 1,700');
        self::assertSelectorTextNotContains('#comparison', '- ');
        self::assertSelectorExists('.comparison-row[data-column="explorer"] img[data-tooltip="Explorer"][data-tooltip-body="Gold · 2,600"]');

        // Two of one day, the other way round
        $this->client->request(Request::METHOD_GET, \sprintf('/agent-stats/timeline?compare=1&compareFrom=%d&compareTo=%d', $evening->id, $morning->id));
        self::assertSelectorTextContains('#comparison-days', 'the same day');
        self::assertSelectorTextContains('.comparison-row[data-column="ap"]', '+ 100,000');

        // Another agent's upload is not compared
        $this->client->request(Request::METHOD_GET, \sprintf('/agent-stats/timeline?compare=1&compareFrom=%d&compareTo=%d', $other->id, $morning->id));
        self::assertSelectorTextContains('#comparison h3', 'Sep 1, 2026');

        // The same upload twice
        $this->client->request(Request::METHOD_GET, \sprintf('/agent-stats/timeline?compare=1&compareFrom=%d&compareTo=%d', $first->id, $first->id));
        self::assertSelectorExists('#compare-same');
        self::assertSelectorNotExists('#comparison');
    }

    public function testNeedsTwoUploads(): void
    {
        $this->createAgentStat($this->user('agent'), '2026-09-01 10:00:00', ap: 1_000_000);

        $this->client->loginUser($this->user('agent'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline?compare=1');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#compare-none');
    }

    private function createAgentStat(User $owner, string $datetime, int $ap, ?int $explorer = null, ?int $recursions = null): AgentStat
    {
        $agentStat = new AgentStat($owner, new AgentStatInput(
            datetime: $datetime,
            nickname: 'TestAgent',
            faction: 'Enlightened',
            ap: $ap,
            level: 16,
            explorer: $explorer,
            recursions: $recursions,
        ));
        $this->entityManager()->persist($agentStat);
        $this->entityManager()->flush();

        return $agentStat;
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
