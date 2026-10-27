<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\DoneBanner;
use App\Entity\User;
use App\Repository\DoneBannerRepository;
use App\Repository\UserRepository;
use App\Security\FakeGoogleIdTokenVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The done list: synced with PATCH /api/me, read and dated with /api/done-banners.
 */
class DoneBannerApiTest extends WebTestCase
{
    private const string GOOGLE_ID = 'gid-done-agent';

    private const string OTHER_GOOGLE_ID = 'gid-done-admin';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->user('agent')->setGoogleId(self::GOOGLE_ID);
        $this->user('admin')->setGoogleId(self::OTHER_GOOGLE_ID);
        $this->entityManager()->flush();
    }

    protected function tearDown(): void
    {
        $this->entityManager()->createQuery('DELETE FROM App\Entity\DoneBanner')->execute();
        $this->entityManager()->createQuery('UPDATE App\Entity\User u SET u.googleId = NULL, u.bannergress = NULL')->execute();

        parent::tearDown();
    }

    public function testASyncKeepsTheDaysOfTheBannersStillOnTheList(): void
    {
        $this->sync(['old-1', 'kept-2', 'gone-3']);
        $kept = $this->banner('kept-2');
        $kept->doneAt = new \DateTimeImmutable('2026-10-01');
        $this->entityManager()->flush();

        // A new banner done, one removed from the list on Bannergress
        $this->sync(['new-0', 'old-1', 'kept-2']);

        $this->request(Request::METHOD_GET, '/api/done-banners');
        self::assertResponseIsSuccessful();
        $banners = $this->responseJson()['member'];
        self::assertIsArray($banners);
        self::assertSame(['new-0', 'old-1', 'kept-2'], \array_column($banners, 'bannerId'));
        // Null values are left out
        self::assertSame([null, null, '2026-10-01'], \array_map(static fn (array $banner): mixed => $banner['doneAt'] ?? null, $banners));
        self::assertSame('Banner kept-2', $banners[2]['title']);
        self::assertSame('/bnrs/pictures/abc', $banners[2]['picture']);
        self::assertSame([50.94, 6.96], [$banners[2]['startLatitude'], $banners[2]['startLongitude']]);
    }

    public function testTheOwnerSetsTheDay(): void
    {
        $this->sync(['quito-1']);
        $id = $this->banner('quito-1')->id;

        $this->request(Request::METHOD_PATCH, '/api/done-banners/'.$id, ['doneAt' => '2026-10-20', 'title' => 'Changed']);

        self::assertResponseIsSuccessful();
        self::assertSame('2026-10-20', $this->responseJson()['doneAt']);
        $this->entityManager()->clear();
        self::assertSame('2026-10-20', $this->banner('quito-1')->doneAt?->format('Y-m-d'));
        // Only the day is writable
        self::assertSame('Banner quito-1', $this->banner('quito-1')->title);

        $this->request(Request::METHOD_PATCH, '/api/done-banners/'.$id, ['doneAt' => null]);
        self::assertResponseIsSuccessful();
        self::assertNull($this->responseJson()['doneAt'] ?? null);
    }

    public function testOthersCannotSeeOrDateTheBanners(): void
    {
        $this->sync(['quito-1']);
        $id = $this->banner('quito-1')->id;

        $this->request(Request::METHOD_PATCH, '/api/done-banners/'.$id, ['doneAt' => '2026-10-20'], self::OTHER_GOOGLE_ID);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->request(Request::METHOD_GET, '/api/done-banners', googleId: self::OTHER_GOOGLE_ID);
        self::assertSame([], $this->responseJson()['member']);
    }

    /**
     * @param list<string> $ids newest first
     */
    private function sync(array $ids): void
    {
        $banners = \array_map(static fn (string $id): array => ['id' => $id, 'title' => 'Banner '.$id, 'missions' => 6, 'picture' => '/bnrs/pictures/abc', 'startLatitude' => 50.94, 'startLongitude' => 6.96], $ids);
        $this->request(Request::METHOD_PATCH, '/api/me', ['bannergress' => ['done' => \count($ids), 'doneBanners' => $banners]]);
        self::assertResponseIsSuccessful();
        $this->entityManager()->clear();
    }

    /**
     * @param array<mixed>|null $payload
     */
    private function request(string $method, string $uri, ?array $payload = null, string $googleId = self::GOOGLE_ID): void
    {
        $server = [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_AUTHORIZATION' => 'Bearer '.FakeGoogleIdTokenVerifier::token($googleId, $googleId.'@example.com'),
        ];
        if (null !== $payload) {
            $server['CONTENT_TYPE'] = 'application/merge-patch+json';
        }

        $this->client->request($method, $uri, server: $server, content: null === $payload ? null : (string) \json_encode($payload));
    }

    /**
     * @return array<mixed>
     */
    private function responseJson(): array
    {
        /** @var array<mixed> $json */
        $json = \json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $json;
    }

    private function banner(string $bannerId): DoneBanner
    {
        $repository = static::getContainer()->get(DoneBannerRepository::class);
        self::assertInstanceOf(DoneBannerRepository::class, $repository);
        $banner = $repository->findOneBy(['bannerId' => $bannerId]);
        self::assertNotNull($banner);

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
