<?php

declare(strict_types=1);

namespace App\Tests\Api;

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
 * GET /api/users: every user with the privacy settings, for admins.
 */
class UserPrivacyApiTest extends WebTestCase
{
    private const string ADMIN_GOOGLE_ID = 'gid-users-admin';

    private const string USER_GOOGLE_ID = 'gid-users-user';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->user('admin')->setGoogleId(self::ADMIN_GOOGLE_ID);
        $this->user('user')->setGoogleId(self::USER_GOOGLE_ID);
        $this->entityManager()->flush();
    }

    protected function tearDown(): void
    {
        $this->entityManager()
            ->createQuery("UPDATE App\\Entity\\User u SET u.googleId = NULL, u.agentName = NULL, u.avatar = NULL, u.avatarSource = NULL, u.visibleParts = '[]', u.visibilityChosenAt = NULL")
            ->execute();

        parent::tearDown();
    }

    public function testOnlyForAdmins(): void
    {
        $this->get(self::USER_GOOGLE_ID);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testListsEveryUserWithThePrivacySettings(): void
    {
        $agent = $this->user('agent')->setAgentName('TestAgent')->setAvatar('data:image/jpeg;base64,cGljdHVyZQ==', null);
        // The picture is private: admins see it anyway
        $agent->chooseVisibleParts([ProfilePart::AGENT, ProfilePart::LATEST_STATS], new \DateTimeImmutable());
        $this->user('admin')->setAgentName('AdminAgent')->chooseVisibleParts([], new \DateTimeImmutable());
        $this->entityManager()->flush();

        $json = $this->get(self::ADMIN_GOOGLE_ID);

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $members */
        $members = $json['member'];
        $byIdentifier = \array_column($members, null, 'identifier');
        // By agent name, those without one last
        $agentNames = \array_map(static fn (array $member): mixed => $member['agentName'] ?? null, $members);
        self::assertSame(['AdminAgent', 'TestAgent'], \array_slice($agentNames, 0, 2));
        self::assertSame([], \array_filter(\array_slice($agentNames, 2)));

        $agent = $byIdentifier['agent'];
        self::assertSame(['agent', 'latest_stats'], $agent['visibleParts']);
        self::assertTrue($agent['visibilityChosen']);
        self::assertSame('data:image/jpeg;base64,cGljdHVyZQ==', $agent['avatar']);
        self::assertSame([], $byIdentifier['admin']['visibleParts']);
        self::assertTrue($byIdentifier['admin']['visibilityChosen']);
        self::assertFalse($byIdentifier['user']['visibilityChosen']);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $googleId): array
    {
        $this->client->request(Request::METHOD_GET, '/api/users', server: [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_AUTHORIZATION' => 'Bearer '.FakeGoogleIdTokenVerifier::token($googleId, $googleId.'@example.com'),
        ]);

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
