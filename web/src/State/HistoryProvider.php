<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\History;
use App\History\HistoryLoader;

/**
 * GET /api/history/{slug}: a document of docs/events/.
 *
 * @implements ProviderInterface<History>
 */
final readonly class HistoryProvider implements ProviderInterface
{
    public function __construct(private HistoryLoader $loader)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): History
    {
        /** @var string $slug the route only allows HistoryLoader::SLUGS */
        $slug = $uriVariables['slug'];
        $document = $this->loader->load($slug);

        return new History($slug, $document->title, $document->blocks);
    }
}
