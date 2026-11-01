<?php

declare(strict_types=1);

namespace App\LinkList;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\LinkList;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A "## " section of the link list with its entries.
 */
final readonly class LinkSection
{
    /**
     * @param list<Link> $links
     */
    public function __construct(
        #[Groups([LinkList::READ])]
        public string $title,
        #[Groups([LinkList::READ])]
        #[ApiProperty(genId: false)]
        public array $links = [],
    ) {
    }
}
