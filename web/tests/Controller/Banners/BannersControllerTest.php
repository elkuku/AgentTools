<?php

declare(strict_types=1);

namespace App\Tests\Controller\Banners;

use App\ApiResource\Bannergress;
use App\Entity\DoneBanner;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BannersControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        $this->entityManager()->createQuery('DELETE FROM App\Entity\DoneBanner')->execute();
        $this->entityManager()->createQuery('UPDATE App\Entity\User u SET u.bannergress = NULL')->execute();

        parent::tearDown();
    }

    public function testShowsAHintBeforeTheFirstSync(): void
    {
        $this->client->loginUser($this->user('agent'));
        $this->client->request(Request::METHOD_GET, '/banners');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#bannergress-none');
        self::assertSelectorExists('nav a[href="/banners"]');
    }

    public function testTheDayOfABannerPutsItOnTheTimeline(): void
    {
        $agent = $this->user('agent');
        $agent->setBannergress(new Bannergress(1, 1, 1, [['id' => 'todo-1', 'title' => 'Next one', 'missions' => 6, 'picture' => '/bnrs/pictures/t1']], publishedBanners: [['id' => 'mine-1', 'title' => 'My own', 'missions' => 12, 'picture' => null, 'startLatitude' => -0.22, 'startLongitude' => -78.51]])->toArray());

        $banner = $this->doneBanner($agent, 'quito-1a2b', 'Quito old town');
        $this->client->loginUser($agent);

        $this->client->request(Request::METHOD_GET, '/banners');
        self::assertResponseIsSuccessful();
        // The world map: a dot for each banner with a start point, the others counted as missing
        $points = \json_decode((string) $this->client->getCrawler()->filter('[data-controller="banner-map"]')->attr('data-banner-map-banners-value'), true);
        self::assertSame([['lat' => -0.22, 'lng' => -78.51, 'list' => 'published', 'title' => 'My own', 'url' => 'https://bannergress.com/banner/mine-1', 'label' => 'Published · 12 missions']], $points);
        self::assertSelectorTextContains('#banner-map-legend', 'no start point yet');
        // The legend filters the lists, with their dots on the map
        self::assertSelectorExists('#banner-map-legend input#banner-map-filter-done[value="done"][checked]');
        self::assertSelectorTextContains('label[for="banner-map-filter-published"]', 'Published 1');
        self::assertSelectorTextContains('label[for="banner-map-filter-todo"]', 'To-do 0');
        self::assertPageTitleContains('My banners');

        // Sections in an accordion: done (open), to-do and published; the map's toggles in that order
        self::assertSame(['banners-done', 'banners-todo', 'banners-published'], $this->client->getCrawler()->filter('#banners .accordion-collapse')->each(static fn ($section): string => (string) $section->attr('id')));
        self::assertSelectorExists('#banners-done.show');
        self::assertSelectorNotExists('#banners-published.show');
        self::assertSame(['done', 'todo', 'published'], $this->client->getCrawler()->filter('#banner-map-legend input')->each(static fn ($input): string => (string) $input->attr('value')));
        self::assertSelectorExists('#bannergress-published-list a[href="https://bannergress.com/banner/mine-1"]');
        self::assertSelectorTextContains('#bannergress-published-list', '12 missions');
        self::assertSelectorExists('#bannergress-todo-list img[src="https://api.bannergress.com/bnrs/pictures/t1"]');
        self::assertSelectorExists('#done-banner-'.$banner->id.' img[src="https://api.bannergress.com/bnrs/pictures/q1"]');
        self::assertSelectorExists('#done-banner-'.$banner->id.' input[type="date"][name="doneAt"][value=""]');

        $this->client->submitForm('Save', ['doneAt' => '2026-10-18']);

        self::assertResponseRedirects('/banners#done-banner-'.$banner->id, Response::HTTP_SEE_OTHER);
        $this->client->followRedirect();
        self::assertSelectorExists('#done-banner-'.$banner->id.' input[name="doneAt"][value="2026-10-18"]');

        // Counted on the stats page, which links to the Bannergress page
        $this->client->request(Request::METHOD_GET, '/agent-stats');
        self::assertSelectorTextContains('#timeline-count-banner', '1');
        self::assertSelectorExists('#bannergress h2 a[href="/banners"]');

        // On the timeline, with a button to change the day
        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline');
        self::assertSelectorTextContains('#timeline-entries .timeline-banner', 'Quito old town');
        self::assertSelectorTextContains('#timeline-entries .timeline-banner', 'Oct 18, 2026');
        // Collapsed: the title, and the picture when opened
        self::assertSelectorTextContains('#timeline-entries .timeline-banner details:not([open]) > summary', 'Quito old town');
        self::assertSelectorExists('#timeline-entries .timeline-banner details .banner-picture-frame > img.banner-picture[src="https://api.bannergress.com/bnrs/pictures/q1"]');
        // A loading animation until the picture is there
        self::assertSelectorExists('#timeline-entries .timeline-banner .banner-picture-frame[data-controller="banner-picture"] .banner-picture-spinner');
        self::assertSelectorExists('#timeline-entries .timeline-banner a.btn[href="/banners#done-banner-'.$banner->id.'"]');
        // The title without a link to Bannergress
        self::assertSelectorNotExists('#timeline-entries .timeline-banner a[href^="https://bannergress.com"]');

        // An empty day takes it off the timeline
        $this->client->request(Request::METHOD_GET, '/banners');
        $this->client->submitForm('Save', ['doneAt' => '']);
        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline');
        self::assertSelectorNotExists('#timeline-entries .timeline-banner');
    }

    public function testRejectsNoDatesAndOthersBanners(): void
    {
        $agent = $this->user('agent');
        $agent->setBannergress(new Bannergress(done: 1)->toArray());

        $banner = $this->doneBanner($agent, 'quito-1a2b', 'Quito old town');

        $this->client->loginUser($this->user('agent'));
        $this->client->request(Request::METHOD_GET, '/banners');
        $this->client->submitForm('Save', ['doneAt' => '2026-02-30']);
        $this->client->followRedirect();
        self::assertAnySelectorTextContains('.alert-danger', 'That is no date.');

        $this->client->loginUser($this->user('admin'));
        $this->client->request(Request::METHOD_POST, '/banners/done/'.$banner->id, ['doneAt' => '2026-10-18']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->entityManager()->clear();
        self::assertNull($this->entityManager()->find(DoneBanner::class, $banner->id)?->doneAt);
    }

    private function doneBanner(User $owner, string $bannerId, string $title): DoneBanner
    {
        $banner = new DoneBanner();
        $banner->owner = $owner;
        $banner->bannerId = $bannerId;
        $banner->title = $title;
        $banner->missions = 18;
        $banner->picture = '/bnrs/pictures/q1';
        $this->entityManager()->persist($banner);
        $this->entityManager()->flush();

        return $banner;
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
