<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\LinkList;
use App\LinkList\LinkListLoader;

/**
 * GET /api/links: the link list of docs/links.md.
 *
 * @implements ProviderInterface<LinkList>
 */
final readonly class LinkListProvider implements ProviderInterface
{
    public function __construct(private LinkListLoader $loader)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): LinkList
    {
        return $this->loader->load();
    }
}
