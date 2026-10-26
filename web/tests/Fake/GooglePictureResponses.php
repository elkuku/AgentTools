<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The test env's HTTP client (framework.http_client.mock_response_factory): nothing leaves the
 * machine. Google profile pictures are a generated image, "…/missing" is a 404.
 */
final class GooglePictureResponses
{
    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        if (\str_ends_with($url, '/missing')) {
            return new MockResponse('', ['http_code' => 404]);
        }

        return new MockResponse(self::png(), ['response_headers' => ['content-type' => 'image/png']]);
    }

    /** A 200 x 150 PNG: not square, to check the crop. */
    public static function png(): string
    {
        $image = \imagecreatetruecolor(200, 150);
        \imagefill($image, 0, 0, (int) \imagecolorallocate($image, 26, 182, 13));
        \ob_start();
        \imagepng($image);

        return (string) \ob_get_clean();
    }
}
