<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\TimelineEvent;
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
 * /api/timeline-events: the app adds, changes and deletes the own custom events; others get them
 * with the stats history (GET /api/agents/{agentName} events).
 */
class TimelineEventApiTest extends WebTestCase
{
    private const string GOOGLE_ID = 'gid-timeline-agent';

    private const string OTHER_GOOGLE_ID = 'gid-timeline-admin';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->user('agent')->setGoogleId(self::GOOGLE_ID);
        $this->user('agent')->setAgentName('TestAgent');
        $this->user('admin')->setGoogleId(self::OTHER_GOOGLE_ID);
        $this->entityManager()->flush();
    }

    protected function tearDown(): void
    {
        $em = $this->entityManager();
        $em->createQuery('DELETE FROM App\Entity\TimelineEvent')->execute();
        $em->createQuery("UPDATE App\\Entity\\User u SET u.googleId = NULL, u.agentName = NULL, u.visibleParts = '[]', u.visibilityChosenAt = NULL")
            ->execute();

        parent::tearDown();
    }

    public function testRequiresToken(): void
    {
        $this->request(Request::METHOD_GET, '/api/timeline-events', null);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAddsChangesAndDeletesAnEvent(): void
    {
        $created = $this->request(Request::METHOD_POST, '/api/timeline-events', self::GOOGLE_ID, ['date' => '2026-10-25', 'text' => 'Mission day']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('2026-10-25', $created['date']);
        self::assertSame('Mission day', $created['text']);
        self::assertIsInt($created['id']);
        self::assertArrayNotHasKey('owner', $created);

        $changed = $this->request(Request::METHOD_PATCH, '/api/timeline-events/'.$created['id'], self::GOOGLE_ID, ['text' => 'Mission day in Quito']);

        self::assertResponseIsSuccessful();
        self::assertSame('2026-10-25', $changed['date']);
        self::assertSame('Mission day in Quito', $changed['text']);

        $this->request(Request::METHOD_DELETE, '/api/timeline-events/'.$created['id']);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame([], $this->request(Request::METHOD_GET, '/api/timeline-events')['member']);
    }

    public function testListsOnlyOwnEventsNewestFirst(): void
    {
        $this->createEvent($this->user('agent'), '2026-10-20', 'Older');
        $this->createEvent($this->user('agent'), '2026-10-25', 'Newer');
        $this->createEvent($this->user('admin'), '2026-10-22', 'Not mine');

        $json = $this->request(Request::METHOD_GET, '/api/timeline-events');

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $member */
        $member = $json['member'];
        self::assertSame(['Newer', 'Older'], \array_column($member, 'text'));
    }

    public function testAnEventNeedsADateAndAText(): void
    {
        $json = $this->request(Request::METHOD_POST, '/api/timeline-events', self::GOOGLE_ID, ['date' => '2026-10-25', 'text' => ' ']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        /** @var list<array<string, mixed>> $violations */
        $violations = $json['violations'];
        self::assertSame(['text'], \array_column($violations, 'propertyPath'));

        $this->request(Request::METHOD_POST, '/api/timeline-events', self::GOOGLE_ID, ['text' => 'No date']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testATravelHasAnEndDateNotBeforeTheDate(): void
    {
        $travel = $this->request(Request::METHOD_POST, '/api/timeline-events', self::GOOGLE_ID, ['date' => '2026-09-20', 'endDate' => '2026-10-02', 'text' => 'Travel to Cuenca']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('2026-10-02', $travel['endDate']);

        // A single day again
        $changed = $this->request(Request::METHOD_PATCH, '/api/timeline-events/'.$travel['id'], self::GOOGLE_ID, ['endDate' => null]);
        self::assertResponseIsSuccessful();
        self::assertNull($changed['endDate'] ?? null);

        $json = $this->request(Request::METHOD_POST, '/api/timeline-events', self::GOOGLE_ID, ['date' => '2026-09-20', 'endDate' => '2026-09-19', 'text' => 'Backwards']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        /** @var list<array<string, mixed>> $violations */
        $violations = $json['violations'];
        self::assertSame(['endDate'], \array_column($violations, 'propertyPath'));
    }

    public function testOthersCannotChangeOrDeleteAnEvent(): void
    {
        $event = $this->createEvent($this->user('agent'), '2026-10-25', 'Mine');

        $this->request(Request::METHOD_PATCH, '/api/timeline-events/'.$event->id, self::OTHER_GOOGLE_ID, ['text' => 'Changed']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->request(Request::METHOD_DELETE, '/api/timeline-events/'.$event->id, self::OTHER_GOOGLE_ID);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOthersGetTheEventsWithTheStatsHistory(): void
    {
        $this->createEvent($this->user('agent'), '2026-10-25', 'Mission day');
        $this->user('agent')->chooseVisibleParts([ProfilePart::AGENT], new \DateTimeImmutable());
        $this->entityManager()->flush();

        self::assertNull($this->request(Request::METHOD_GET, '/api/agents/TestAgent', self::OTHER_GOOGLE_ID)['events'] ?? null);

        $this->user('agent')->chooseVisibleParts([ProfilePart::AGENT, ProfilePart::STATS_HISTORY], new \DateTimeImmutable());
        $this->entityManager()->flush();

        self::assertSame(
            [['date' => '2026-10-25', 'endDate' => null, 'text' => 'Mission day']],
            $this->request(Request::METHOD_GET, '/api/agents/TestAgent', self::OTHER_GOOGLE_ID)['events'],
        );
    }

    private function createEvent(User $owner, string $date, string $text): TimelineEvent
    {
        $event = new TimelineEvent();
        $event->owner = $owner;
        $event->date = new \DateTimeImmutable($date);
        $event->text = $text;
        $this->entityManager()->persist($event);
        $this->entityManager()->flush();

        return $event;
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, ?string $googleId = self::GOOGLE_ID, ?array $payload = null): array
    {
        $server = ['HTTP_ACCEPT' => 'application/ld+json'];
        if (null !== $googleId) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.FakeGoogleIdTokenVerifier::token($googleId, $googleId.'@example.com');
        }

        if (null !== $payload) {
            $server['CONTENT_TYPE'] = Request::METHOD_PATCH === $method ? 'application/merge-patch+json' : 'application/ld+json';
        }

        $this->client->request($method, $uri, server: $server, content: null === $payload ? null : (string) \json_encode($payload));

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
