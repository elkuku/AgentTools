<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\AvatarUpdater;
use App\Tests\Fake\GooglePictureResponses;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AvatarUpdaterTest extends TestCase
{
    private const string PICTURE = 'https://lh3.googleusercontent.com/a/picture';

    public function testStoresA100PxSquareJpeg(): void
    {
        $user = new User();
        $this->updater(new MockHttpClient(new MockResponse(GooglePictureResponses::png())), flushes: 1)->update($user, self::PICTURE);

        $avatar = (string) $user->getAvatar();
        self::assertStringStartsWith('data:image/jpeg;base64,', $avatar);
        $size = \getimagesizefromstring((string) \base64_decode(\substr($avatar, \strlen('data:image/jpeg;base64,')), true));
        self::assertNotFalse($size);
        self::assertSame([100, 100, \IMAGETYPE_JPEG], [$size[0], $size[1], $size[2]]);
        self::assertSame(self::PICTURE, $user->getAvatarSource());
    }

    public function testFetchesAgainOnlyWhenThePictureChanges(): void
    {
        $user = new User()->setAvatar('data:image/jpeg;base64,old', self::PICTURE);
        $client = new MockHttpClient(new MockResponse(GooglePictureResponses::png()));

        $this->updater($client, flushes: 0)->update($user, self::PICTURE);
        self::assertSame(0, $client->getRequestsCount());

        $this->updater($client, flushes: 1)->update($user, self::PICTURE.'2');
        self::assertSame(1, $client->getRequestsCount());
        self::assertNotSame('data:image/jpeg;base64,old', $user->getAvatar());
    }

    public function testFetchesOnlyGooglePictures(): void
    {
        $client = new MockHttpClient(new MockResponse(GooglePictureResponses::png()));
        $user = new User();

        foreach ([null, 42, '', 'http://lh3.googleusercontent.com/a', 'https://example.com/a.png', 'https://googleusercontent.com.example.com/a'] as $url) {
            $this->updater($client, flushes: 0)->update($user, $url);
        }

        self::assertSame(0, $client->getRequestsCount());
        self::assertNull($user->getAvatar());
    }

    public function testAFailureKeepsTheOldPicture(): void
    {
        $user = new User()->setAvatar('data:image/jpeg;base64,old', self::PICTURE);

        $this->updater(new MockHttpClient(new MockResponse('', ['http_code' => 404])), flushes: 0)->update($user, self::PICTURE.'2');
        $this->updater(new MockHttpClient(new MockResponse('<html>not an image</html>')), flushes: 0)->update($user, self::PICTURE.'2');

        self::assertSame('data:image/jpeg;base64,old', $user->getAvatar());
        self::assertSame(self::PICTURE, $user->getAvatarSource());
    }

    private function updater(MockHttpClient $client, int $flushes): AvatarUpdater
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly($flushes))->method('flush');

        return new AvatarUpdater($client, $entityManager, new NullLogger());
    }
}
