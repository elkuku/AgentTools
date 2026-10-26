<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Keeps the user's Google profile picture (the ID token's "picture" claim) as a 100 px JPEG data:
 * URI, as 4e-website's AvatarHelper: pages show it without loading anything from Google. It is
 * fetched again only when the URL changes; a failure keeps the old picture and never stops a sign-in.
 */
final readonly class AvatarUpdater
{
    public const int SIZE = 100;

    /** Google serves profile pictures from here; nothing else is fetched. */
    private const string PICTURE_HOST = '.googleusercontent.com';

    private const int MAX_BYTES = 2_000_000;

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function update(User $user, mixed $pictureUrl): void
    {
        if (!\is_string($pictureUrl) || !$this->isGooglePicture($pictureUrl)
            || ($pictureUrl === $user->getAvatarSource() && null !== $user->getAvatar())
        ) {
            return;
        }

        $avatar = $this->fetch($pictureUrl);
        if (null === $avatar) {
            return;
        }

        $user->setAvatar($avatar, $pictureUrl);
        $this->entityManager->flush();
    }

    private function isGooglePicture(string $url): bool
    {
        $host = \parse_url($url, \PHP_URL_HOST);

        return \str_starts_with($url, 'https://') && \is_string($host) && \str_ends_with($host, self::PICTURE_HOST);
    }

    /** The picture as a data: URI, or null (logged) when it can't be fetched or read. */
    private function fetch(string $url): ?string
    {
        try {
            $content = $this->httpClient->request('GET', $url, ['timeout' => 5, 'max_redirects' => 0])->getContent();
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('Fetching the Google profile picture failed: {message}', ['message' => $exception->getMessage()]);

            return null;
        }

        $image = \strlen($content) <= self::MAX_BYTES ? @\imagecreatefromstring($content) : false;
        if (false === $image) {
            $this->logger->warning('The Google profile picture is not an image.');

            return null;
        }

        // A centered square, scaled down
        $side = \min(\imagesx($image), \imagesy($image));
        $square = \imagecreatetruecolor(self::SIZE, self::SIZE);
        \imagecopyresampled($square, $image, 0, 0, \intdiv(\imagesx($image) - $side, 2), \intdiv(\imagesy($image) - $side, 2), self::SIZE, self::SIZE, $side, $side);

        \ob_start();
        \imagejpeg($square, null, 85);

        return 'data:image/jpeg;base64,'.\base64_encode((string) \ob_get_clean());
    }
}
