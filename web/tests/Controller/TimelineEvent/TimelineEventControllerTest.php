<?php

declare(strict_types=1);

namespace App\Tests\Controller\TimelineEvent;

use App\Entity\TimelineEvent;
use App\Entity\User;
use App\Repository\TimelineEventRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom events on the own timeline (/agent-stats): add, edit and delete.
 */
class TimelineEventControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        $this->entityManager()->createQuery('DELETE FROM App\Entity\TimelineEvent')->execute();

        parent::tearDown();
    }

    public function testAddsAnEventToTheTimeline(): void
    {
        $this->client->loginUser($this->user('agent'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline');
        self::assertResponseIsSuccessful();
        // In a modal, closed at first, opened from the title row
        self::assertSelectorExists('#timeline-add.modal[data-modal-open-value="false"] form');
        self::assertSelectorExists('a#timeline-add-open[data-bs-toggle="modal"][href="#timeline-add"]');

        $this->client->submitForm('Add event', [
            'timeline_event[date]' => '2026-10-25',
            'timeline_event[text]' => "Mission day\nin Quito",
        ]);

        self::assertResponseRedirects('/agent-stats/timeline');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'The event has been added.');
        self::assertSelectorTextContains('#timeline-entries .timeline-event', 'Mission day');
        self::assertSelectorTextContains('#timeline-entries .timeline-event', 'Oct 25, 2026');

        $events = $this->events()->findByOwner($this->user('agent'));
        self::assertCount(1, $events);
        self::assertSame("Mission day\nin Quito", $events[0]->text);
    }

    public function testATravelIsShownWithItsDates(): void
    {
        $this->client->loginUser($this->user('agent'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline');

        $this->client->submitForm('Add event', [
            'timeline_event[date]' => '2026-09-20',
            'timeline_event[endDate]' => '2026-10-02',
            'timeline_event[text]' => 'Travel to Cuenca',
        ]);
        $this->client->followRedirect();

        // Its end and its start, each with its day
        $event = $this->events()->findByOwner($this->user('agent'))[0];
        self::assertSelectorTextContains('#timeline-event-'.$event->id.'-end.timeline-travel-end', 'Oct 2, 2026 · End of the travel');
        self::assertSelectorTextContains('#timeline-event-'.$event->id.'.timeline-travel-start', 'Sep 20, 2026 · Start of the travel');
        self::assertSelectorExists('#timeline-event-'.$event->id.'.timeline-travel-start i.bi-airplane-fill');
        self::assertSelectorTextContains('#timeline-event-'.$event->id.'-end', 'Travel to Cuenca');
        // A band on the chart, with its dates and text as the tooltip
        self::assertSelectorTextContains('#timeline-chart .chart-band title', '2026-09-20 – 2026-10-02: Travel to Cuenca');
    }

    public function testTheEndDateIsNotBeforeTheDate(): void
    {
        $this->client->loginUser($this->user('agent'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline');

        $this->client->submitForm('Add event', [
            'timeline_event[date]' => '2026-09-20',
            'timeline_event[endDate]' => '2026-09-19',
            'timeline_event[text]' => 'Backwards',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('#timeline-add .invalid-feedback', 'The end date must not be before the date.');
    }

    public function testTravelsCannotOverlap(): void
    {
        $this->client->loginUser($this->user('agent'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline');
        $this->client->submitForm('Add event', [
            'timeline_event[date]' => '2026-09-20',
            'timeline_event[endDate]' => '2026-10-02',
            'timeline_event[text]' => 'Travel to Cuenca',
        ]);
        $this->client->followRedirect();

        // Sharing a day
        $this->client->submitForm('Add event', [
            'timeline_event[date]' => '2026-10-02',
            'timeline_event[endDate]' => '2026-10-05',
            'timeline_event[text]' => 'Travel to Lima',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('#timeline-add .invalid-feedback', 'Travels cannot overlap: Travel to Cuenca (2026-09-20 – 2026-10-02).');

        // The day after, and a single-day event during the travel
        $this->client->submitForm('Add event', [
            'timeline_event[date]' => '2026-10-03',
            'timeline_event[endDate]' => '2026-10-05',
            'timeline_event[text]' => 'Travel to Lima',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        $this->client->submitForm('Add event', ['timeline_event[date]' => '2026-09-25', 'timeline_event[text]' => 'Mission day']);
        self::assertResponseRedirects();
        self::assertCount(3, $this->events()->findByOwner($this->user('agent')));

        // Editing a travel keeps it apart from the others, not from itself
        $cuenca = \array_last($this->events()->findByOwner($this->user('agent')));
        $this->client->request(Request::METHOD_GET, '/timeline-events/'.$cuenca->id.'/edit');
        $this->client->submitForm('Save', ['timeline_event[endDate]' => '2026-10-04']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->client->submitForm('Save', ['timeline_event[endDate]' => '2026-10-01']);
        self::assertResponseRedirects('/agent-stats/timeline');
    }

    public function testAnEventNeedsAText(): void
    {
        $this->client->loginUser($this->user('agent'));
        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline');

        $this->client->submitForm('Add event', ['timeline_event[date]' => '2026-10-25', 'timeline_event[text]' => '  ']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        // The modal opens again with the error
        self::assertSelectorExists('#timeline-add.modal[data-modal-open-value="true"] .invalid-feedback');
        self::assertCount(0, $this->events()->findByOwner($this->user('agent')));
    }

    public function testEditsAndDeletesAnOwnEvent(): void
    {
        $event = $this->createEvent($this->user('agent'), 'Old text');
        $this->client->loginUser($this->user('agent'));

        $this->client->request(Request::METHOD_GET, '/agent-stats/timeline');
        $this->client->click($this->client->getCrawler()->filter('#timeline-event-'.$event->id.' a[title="edit"]')->link());
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Save', ['timeline_event[text]' => 'New text']);

        self::assertResponseRedirects('/agent-stats/timeline');
        $this->client->followRedirect();
        self::assertSelectorTextContains('#timeline-event-'.$event->id, 'New text');

        $this->client->submit($this->client->getCrawler()->filter('#timeline-event-'.$event->id.' form')->form());

        self::assertResponseRedirects('/agent-stats/timeline');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'The event has been deleted.');
        self::assertCount(0, $this->events()->findByOwner($this->user('agent')));
    }

    public function testOthersCannotEditOrDeleteAnEvent(): void
    {
        $event = $this->createEvent($this->user('agent'), 'Mine');
        $this->client->loginUser($this->user('admin'));

        $this->client->request(Request::METHOD_GET, '/timeline-events/'.$event->id.'/edit');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->request(Request::METHOD_POST, '/timeline-events/'.$event->id.'/delete');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertCount(1, $this->events()->findByOwner($this->user('agent')));
    }

    private function createEvent(User $owner, string $text): TimelineEvent
    {
        $event = new TimelineEvent();
        $event->owner = $owner;
        $event->date = new \DateTimeImmutable('2026-10-20');
        $event->text = $text;
        $this->entityManager()->persist($event);
        $this->entityManager()->flush();

        return $event;
    }

    private function events(): TimelineEventRepository
    {
        $repository = static::getContainer()->get(TimelineEventRepository::class);
        self::assertInstanceOf(TimelineEventRepository::class, $repository);

        return $repository;
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
