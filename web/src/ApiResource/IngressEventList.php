<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\IngressEvents\IngressEvent;
use App\State\IngressEventListProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * GET /api/events: the Ingress events (IngressEventSource), for everyone. The website's
 * /events page shows the same.
 */
#[ApiResource(
    description: 'The Ingress events gathered from ingress.com/news by app:events:fetch: type, title, start, end (Y-m-d), startsAt and endsAt (UTC, or null for local time), location, url, source, confirmed. Public, no sign-in needed.',
    operations: [
        new Get(
            uriTemplate: '/events',
            cacheHeaders: ['max_age' => 3600, 'public' => true],
            provider: IngressEventListProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::READ]],
)]
final readonly class IngressEventList
{
    public const string READ = 'ingress-event-list:read';

    /**
     * @param list<IngressEvent> $events by date
     */
    public function __construct(
        #[Groups([self::READ])]
        #[ApiProperty(genId: false)]
        public array $events = [],
        /** When the list last changed, ISO 8601. */
        #[Groups([self::READ])]
        public ?string $generatedAt = null,
    ) {
    }
}
