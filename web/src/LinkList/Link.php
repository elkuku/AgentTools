<?php

declare(strict_types=1);

namespace App\LinkList;

use App\ApiResource\LinkList;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * An entry of the link list: "- [Title](https://…) — Description".
 */
final readonly class Link
{
    public function __construct(
        #[Groups([LinkList::READ])]
        public string $title,
        #[Groups([LinkList::READ])]
        public string $url,
        /** Plain text, null without one. */
        #[Groups([LinkList::READ])]
        public ?string $description = null,
    ) {
    }
}
