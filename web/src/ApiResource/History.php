<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\History\HistoryBlock;
use App\State\HistoryProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * GET /api/history/{slug}: a document of docs/events/ as blocks (HistoryParser), for everyone. The
 * website's /history page shows the same.
 */
#[ApiResource(
    description: 'A history document, anomalies, mission-days, operations or ingress-years, from docs/events/ in the repository: its title and blocks (heading, paragraph, list, table) of text with bold, links and medals. Public, no sign-in needed.',
    operations: [
        new Get(
            uriTemplate: '/history/{slug}',
            requirements: ['slug' => 'anomalies|mission-days|operations|ingress-years'],
            cacheHeaders: ['max_age' => 3600, 'public' => true],
            provider: HistoryProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::READ]],
)]
final readonly class History
{
    public const string READ = 'history:read';

    /**
     * @param list<HistoryBlock> $blocks
     */
    public function __construct(
        #[Groups([self::READ])]
        #[ApiProperty(identifier: true)]
        public string $slug,
        #[Groups([self::READ])]
        public string $title,
        #[Groups([self::READ])]
        #[ApiProperty(genId: false)]
        public array $blocks = [],
    ) {
    }
}
