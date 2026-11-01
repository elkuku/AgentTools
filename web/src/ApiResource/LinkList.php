<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\LinkList\LinkSection;
use App\State\LinkListProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * GET /api/links: the Ingress link list of docs/links.md (LinkListParser), for everyone. The
 * website's /links page shows the same.
 */
#[ApiResource(
    description: 'The Ingress link list, from docs/links.md in the repository (changed with pull requests, live after the next deploy): sections with a title and links (title, url, description or null). Public, no sign-in needed.',
    operations: [
        new Get(
            uriTemplate: '/links',
            cacheHeaders: ['max_age' => 3600, 'public' => true],
            provider: LinkListProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::READ]],
)]
final readonly class LinkList
{
    public const string READ = 'link-list:read';

    /**
     * @param list<LinkSection> $sections
     * @param list<string>      $problems entries not in the list's format, with their line; not in the API
     */
    public function __construct(
        #[Groups([self::READ])]
        #[ApiProperty(genId: false)]
        public array $sections = [],
        public array $problems = [],
    ) {
    }
}
