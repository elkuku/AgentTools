<?php

declare(strict_types=1);

namespace App\Tests\Controller\Agent;

use App\ApiResource\Bannergress;
use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\DoneBanner;
use App\Entity\TimelineEvent;
use App\Entity\User;
use App\Enum\ProfilePart;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // The agent shows something, the admin is the one looking
        $this->user('agent')->setAgentName('TestAgent');
        $this->user('user')->setAgentName('PrivateAgent');
        $this->entityManager()->flush();
    }

    protected function tearDown(): void
    {
        $em = $this->entityManager();
        $em->createQuery('DELETE FROM App\Entity\AgentStat')->execute();
        $em->createQuery('DELETE FROM App\Entity\TimelineEvent')->execute();
        $em->createQuery('DELETE FROM App\Entity\DoneBanner')->execute();
        $em->createQuery("UPDATE App\\Entity\\User u SET u.agentName = NULL, u.visibleParts = '[]', u.visibilityChosenAt = NULL, u.customMedals = '{}', u.bannergress = NULL")
            ->execute();

        parent::tearDown();
    }

    public function testListsOnlyAgentsWhoShowTheirProfile(): void
    {
        $this->show('agent', [ProfilePart::AGENT]);
        // Without the agent part nothing is shown
        $this->show('user', [ProfilePart::LATEST_STATS]);
        $this->createAgentStat($this->user('agent'), '2026-10-19 15:35:56', 16);

        $this->client->loginUser($this->user('admin'));
        $this->client->request(Request::METHOD_GET, '/agents');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#agents', 'TestAgent');
        self::assertSelectorExists('#agents img.faction-logo-small[alt="ENL"]');
        self::assertSelectorTextNotContains('#agents', 'Enlightened');
        self::assertSelectorTextNotContains('#agents', 'PrivateAgent');
    }

    public function testTheUsersPageHasEveryUserWithThePrivacySettings(): void
    {
        $this->show('agent', [ProfilePart::AGENT, ProfilePart::CUSTOM_MEDALS]);
        $this->show('user', []);

        $this->client->loginUser($this->user('admin'));
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/agents');

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Admin agents');
        // Every user, the private ones too, with their identifier
        self::assertSelectorTextContains('#users', 'PrivateAgent');
        self::assertSelectorTextContains('#users', 'admin');
        self::assertCount(1, $crawler->filter('#users a[href="/agents/TestAgent"]'));
        // Only the visible agent opens
        self::assertCount(0, $crawler->filter('#users a[href="/agents/PrivateAgent"]'));
        self::assertSelectorExists('#users .privacy-icons i.bi-person[title="Agent name, faction and level"]');
        self::assertSelectorExists('#users .privacy-icons i.bi-award');
        self::assertSelectorExists('#users .privacy-icons i.bi-lock[title="All private"]');
        // The admin has not chosen
        self::assertSelectorExists('#users .privacy-icons i.bi-question-circle');
    }

    public function testPrivateAgentsHaveNoPage(): void
    {
        $this->show('user', [ProfilePart::LATEST_STATS, ProfilePart::STATS_HISTORY]);
        $this->client->loginUser($this->user('admin'));

        foreach (['/agents/PrivateAgent', '/agents/NoSuchAgent'] as $path) {
            $this->client->request(Request::METHOD_GET, $path);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }
    }

    public function testShowsOnlyTheVisibleParts(): void
    {
        $this->show('agent', [ProfilePart::AGENT, ProfilePart::LATEST_STATS]);
        $this->createAgentStat($this->user('agent'), '2026-10-19 15:35:56', 16);
        // Events come with the stats history only
        $this->createEvent($this->user('agent'), '2026-10-19', 'A private note');

        $this->client->loginUser($this->user('admin'));
        // Agent names ignore case
        $this->client->request(Request::METHOD_GET, '/agents/testagent');

        self::assertResponseIsSuccessful();
        // As in the app: name, level, AP and the medals (all locked without stats for them)
        self::assertSelectorTextContains('#agent-stats', 'TestAgent');
        self::assertSelectorTextContains('#agent-stats', 'L16');
        // The faction's logo and color, as on 4e-website
        self::assertSelectorExists('#agent-stats img.faction-logo[alt="ENL"][src*="images/logos/ENL"]');
        self::assertSelectorExists('#agent-level.faction-ENL');
        self::assertSelectorTextNotContains('#agent-stats', 'Enlightened');
        self::assertSelectorTextContains('#agent-stats', 'AP: 243,517,130');
        self::assertSelectorExists('#medals img[alt="Explorer: locked"]');
        // Discontinued and not earned
        self::assertSelectorNotExists('#medals img[alt^="Seer"]');
        self::assertSelectorTextContains('#agent-stats', 'Latest update: 2026-10-19');
        // The first import tells about the history
        self::assertSelectorTextNotContains('#agent-stats', 'First import');
        self::assertSelectorNotExists('#timeline-entries');
        self::assertSelectorTextNotContains('body', 'A private note');
        self::assertSelectorNotExists('#custom-medals');
    }

    public function testShowsTheHistoryAndTheCustomMedals(): void
    {
        $agent = $this->user('agent');
        $agent->setCustomMedals(['anomaly' => ['orion' => 'platinum', 'echo' => ''], 'annual' => ['voyager' => 5], 'event' => []]);
        $this->show('agent', [ProfilePart::AGENT, ProfilePart::STATS_HISTORY, ProfilePart::CUSTOM_MEDALS]);
        $this->createAgentStat($agent, '2026-10-19 15:35:56', 15);
        $this->createAgentStat($agent, '2026-10-20 15:35:56', 16);
        $this->createEvent($agent, '2026-10-20', 'Anomaly in Quito');

        $this->client->loginUser($this->user('admin'));
        $crawler = $this->client->request(Request::METHOD_GET, '/agents/TestAgent');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#agent-stats');
        self::assertSelectorTextContains('#agent-name', 'TestAgent');
        self::assertSelectorTextSame('.lead', 'level 16');
        self::assertSelectorExists('#agent-level.faction-ENL');
        self::assertSelectorExists('h1 img.faction-logo[alt="ENL"]');
        // The timeline: the event at the end of its day, then the uploads, newest first
        self::assertCount(2, $crawler->filter('#timeline-entries .timeline-upload'));
        self::assertSelectorTextContains('#timeline-entries li:nth-child(1).timeline-event', 'Anomaly in Quito');
        self::assertSelectorTextContains('#timeline-entries li:nth-child(2)', '2026-10-20 15:35');
        self::assertSelectorTextContains('#timeline-entries li:nth-child(2)', 'New level 16');
        self::assertSelectorTextContains('#timeline-entries li:nth-child(3)', 'First upload');
        // Filters by kind and year
        self::assertSelectorExists('#timeline-filters input#timeline-filter-travel[value="travel"]');
        self::assertSame(['', '2026'], $crawler->filter('#timeline-filter-year option')->each(static fn ($option): string => (string) $option->attr('value')));
        self::assertSelectorExists('#timeline-entries li.timeline-event[data-kind="event"][data-years="2026"]');
        self::assertSelectorExists('#timeline-entries li.timeline-upload[data-kind="upload"]');
        // Only uploads with medal upgrades are marked for the medal filter: the first has nothing to compare
        self::assertSelectorExists('#timeline-filters input#timeline-filter-medals');
        self::assertCount(0, $crawler->filter('#timeline-entries li.timeline-upload[data-medals]'));
        // Read-only for others
        self::assertSelectorNotExists('#timeline-entries a[href*="/timeline-events/"]');
        self::assertSelectorNotExists('#timeline-entries a[href^="/agent-stats/"]');
        self::assertSelectorExists('#custom-medals img[alt="Orion (Platinum)"][src*="anomaly_orion_platinum"]');
        self::assertSelectorExists('#custom-medals img[alt="Echo"][src*="anomaly_echo"]');
        // Annual medals are stored as levels
        self::assertSelectorExists('#custom-medals img[alt="Voyager (Onyx)"][src*="badge_voyager_black"]');
        self::assertSelectorTextNotContains('#custom-medals', 'Event');
    }

    public function testShowsBannergressOnlyWithItsPart(): void
    {
        $agent = $this->user('agent');
        $agent->setBannergress(new Bannergress(2, 5, 1234)->toArray());
        $this->createAgentStat($agent, '2026-10-19 15:35:56', 16);
        $banner = new DoneBanner();
        $banner->owner = $agent;
        $banner->bannerId = 'quito-1a2b';
        $banner->title = 'Quito old town';
        $banner->missions = 18;
        $banner->doneAt = new \DateTimeImmutable('2026-10-18');
        $this->entityManager()->persist($banner);
        $this->show('agent', [ProfilePart::AGENT, ProfilePart::STATS_HISTORY]);

        $this->client->loginUser($this->user('admin'));
        $this->client->request(Request::METHOD_GET, '/agents/TestAgent');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#bannergress');
        // Banners are on the timeline only with the Bannergress part
        self::assertSelectorNotExists('#timeline-entries .timeline-banner');
        self::assertSelectorNotExists('#timeline-filter-banner');
        $this->client->request(Request::METHOD_GET, '/agents/TestAgent/banners');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->show('agent', [ProfilePart::AGENT, ProfilePart::STATS_HISTORY, ProfilePart::BANNERGRESS]);
        $this->client->request(Request::METHOD_GET, '/agents/TestAgent');

        // The counts, linking to the agent's Bannergress page
        self::assertSelectorTextSame('#bannergress-published', '2');
        self::assertSelectorTextSame('#bannergress-done', '1,234');
        self::assertSelectorExists('#bannergress h2 a[href="/agents/TestAgent/banners"]');
        self::assertSelectorTextContains('#timeline-entries li.timeline-banner[data-kind="banner"]', 'Quito old town');
        self::assertSelectorTextContains('#timeline-entries li.timeline-banner', '2026-10-18');
        self::assertSelectorExists('#timeline-filter-banner');
        self::assertSelectorNotExists('#timeline-entries .timeline-banner a[href^="/banners"]');

        $this->client->request(Request::METHOD_GET, '/agents/TestAgent/banners');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#bannergress-done-list a[href="https://bannergress.com/banner/quito-1a2b"]');
        self::assertSelectorTextContains('#bannergress-done-list', 'Done on 2026-10-18');
        // Read-only
        self::assertSelectorNotExists('#bannergress-done-list form');
    }

    public function testUploadsWithMedalUpgradesAreMarkedForTheFilter(): void
    {
        $agent = $this->user('agent');
        $this->show('agent', [ProfilePart::AGENT, ProfilePart::STATS_HISTORY]);
        $this->createAgentStat($agent, '2026-10-19 15:35:56', 16, explorer: 900);
        // Explorer from bronze to gold
        $this->createAgentStat($agent, '2026-10-20 15:35:56', 16, explorer: 2500);
        $this->createAgentStat($agent, '2026-10-21 15:35:56', 16, explorer: 2600);

        $this->client->loginUser($this->user('admin'));
        $crawler = $this->client->request(Request::METHOD_GET, '/agents/TestAgent');

        $marked = $crawler->filter('#timeline-entries li.timeline-upload[data-medals]');
        self::assertCount(1, $marked);
        self::assertStringContainsString('2026-10-20 15:35', $marked->text());
    }

    public function testTheAgentSeesTheOwnPageAsOthersDo(): void
    {
        $this->show('agent', [ProfilePart::AGENT]);

        $this->client->loginUser($this->user('agent'));
        $this->client->request(Request::METHOD_GET, '/agents/TestAgent');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-info', 'This is how other users see your profile.');
    }

    /**
     * @param list<ProfilePart> $parts
     */
    private function show(string $identifier, array $parts): void
    {
        $this->user($identifier)->chooseVisibleParts($parts, new \DateTimeImmutable());
        $this->entityManager()->flush();
    }

    private function createAgentStat(User $owner, string $datetime, int $level, ?int $explorer = null): void
    {
        $this->entityManager()->persist(new AgentStat($owner, new AgentStatInput(
            datetime: $datetime,
            nickname: (string) $owner->getAgentName(),
            faction: 'Enlightened',
            ap: 243517130,
            level: $level,
            explorer: $explorer,
        )));
        $this->entityManager()->flush();
    }

    private function createEvent(User $owner, string $date, string $text): void
    {
        $event = new TimelineEvent();
        $event->owner = $owner;
        $event->date = new \DateTimeImmutable($date);
        $event->text = $text;
        $this->entityManager()->persist($event);
        $this->entityManager()->flush();
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
