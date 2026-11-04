<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\IngressEventList;
use App\IngressEvents\IngressEventSource;

/**
 * GET /api/events: the events published on GitHub Pages.
 *
 * @implements ProviderInterface<IngressEventList>
 */
final readonly class IngressEventListProvider implements ProviderInterface
{
    public function __construct(private IngressEventSource $source)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): IngressEventList
    {
        return $this->source->load();
    }
}
