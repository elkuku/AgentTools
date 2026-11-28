<?php

declare(strict_types=1);

namespace App\Tests\IngressEvents;

use App\IngressEvents\BadgeCatalog;
use App\IngressEvents\EventType;
use App\IngressEvents\IngressEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class BadgeCatalogTest extends TestCase
{
    public function testTheEventsGetTheirMedals(): void
    {
        $badge = static fn (string $id, string $title, string $updated = '2026-01-01'): array => ['id' => $id, 'collectionId' => 'badges', 'title' => $title, 'image' => [$id.'_bronze.png', $id.'_gold.png'], 'updated' => $updated];
        $catalog = new BadgeCatalog(new MockHttpClient(new JsonMockResponse(['items' => [
            $badge('deploy', 'Cygnus: Global Deploy'),
            $badge('crystal', 'Crystal Horizon 2026'),
            $badge('toast2022', 'Toast Day 2022'),
            $badge('moonlit', 'Moonlit Paths'),
        ]])));
        $event = static fn (string $title, string $start = '2026-12-02'): IngressEvent => new IngressEvent(EventType::Global, $title, $start, $start);

        $events = $catalog->withMedals([
            $event('Cygnus Global Deploy'),
            $event('Crystal Horizon'),
            // The badge of another year
            $event('Ingress Toast Day 2026', '2026-02-26'),
            $event('Operation Moonlit Paths', '2026-07-15'),
            $event('Something else'),
        ]);

        self::assertSame(['Cygnus: Global Deploy', 'Crystal Horizon 2026', null, 'Moonlit Paths', null], \array_map(static fn (IngressEvent $event): ?string => $event->medal, $events));
        // The last (top) image
        self::assertSame('https://ingress.plus/api/files/badges/deploy/deploy_gold.png', $events[0]->medalImage);
    }

    public function testWithoutIngressPlusTheEventsStay(): void
    {
        $catalog = new BadgeCatalog(new MockHttpClient(new MockResponse('', ['http_code' => 503])));
        $event = new IngressEvent(EventType::Global, 'Crystal Horizon', '2026-12-02', '2026-12-21');

        self::assertSame([$event], $catalog->withMedals([$event]));
    }
}
