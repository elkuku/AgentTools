<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\ProfilePart;
use App\Repository\UserRepository;
use App\Security\FakeGoogleIdTokenVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Google profile picture: GooglePictureResponses answers the picture requests.
 */
class AvatarTest extends WebTestCase
{
    private const string PICTURE = 'https://lh3.googleusercontent.com/a/picture';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        $this->entityManager()
            ->createQuery("UPDATE App\\Entity\\User u SET u.googleId = NULL, u.agentName = NULL, u.avatar = NULL, u.avatarSource = NULL, u.visibleParts = '[]', u.visibilityChosenAt = NULL")
            ->execute();

        parent::tearDown();
    }

    public function testAWebSignInStoresThePicture(): void
    {
        $this->client->request(Request::METHOD_POST, '/connect/google/verify', [
            'credential' => FakeGoogleIdTokenVerifier::token('gid-avatar', 'user', picture: self::PICTURE),
        ]);
        self::assertResponseRedirects();

        $this->entityManager()->clear();
        self::assertStringStartsWith('data:image/jpeg;base64,', (string) $this->user('user')->getAvatar());

        $this->client->request(Request::METHOD_GET, '/profile');
        self::assertSelectorExists('#avatar[src^="data:image/jpeg;base64,"]');
        self::assertSelectorExists('nav img.avatar');
    }

    public function testTheAppStoresThePicture(): void
    {
        $token = FakeGoogleIdTokenVerifier::token('gid-avatar-app', 'user', picture: self::PICTURE);
        $this->client->request(Request::METHOD_GET, '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseIsSuccessful();
        $this->entityManager()->clear();
        self::assertSame(self::PICTURE, $this->user('user')->getAvatarSource());

        // The app shows it from /api/me
        $this->client->request(Request::METHOD_GET, '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $json = \json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($json);
        self::assertStringStartsWith('data:image/jpeg;base64,', (string) $json['avatar']);
    }

    public function testOtherUsersSeeThePictureOnlyWhenVisible(): void
    {
        $agent = $this->user('agent')->setAgentName('TestAgent')->setAvatar('data:image/jpeg;base64,cGljdHVyZQ==', self::PICTURE);
        $agent->chooseVisibleParts([ProfilePart::AGENT], new \DateTimeImmutable());
        $this->entityManager()->flush();
        $this->client->loginUser($this->user('admin'));

        $this->client->request(Request::METHOD_GET, '/agents');
        self::assertSelectorNotExists('#agents img.avatar');
        $this->client->request(Request::METHOD_GET, '/agents/TestAgent');
        self::assertSelectorNotExists('img.avatar');

        $agent = $this->user('agent');
        $agent->chooseVisibleParts([ProfilePart::AGENT, ProfilePart::AVATAR], new \DateTimeImmutable());
        $this->entityManager()->flush();

        $this->client->request(Request::METHOD_GET, '/agents');
        self::assertSelectorExists('#agents img.avatar[src="data:image/jpeg;base64,cGljdHVyZQ=="]');
        $this->client->request(Request::METHOD_GET, '/agents/TestAgent');
        self::assertSelectorExists('h1 img.avatar');
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
