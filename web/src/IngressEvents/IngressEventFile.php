<?php

declare(strict_types=1);

namespace App\IngressEvents;

use App\ApiResource\IngressEventList;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * data/ingress-events.json, written by app:events:fetch: the list the calendar falls back to
 * (IngressEventSource). Without the file, or with a broken one, the list is empty.
 */
final readonly class IngressEventFile
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/data/ingress-events.json')]
        private string $file,
    ) {
    }

    public function path(): string
    {
        return $this->file;
    }

    public function load(?string $file = null): IngressEventList
    {
        $content = @\file_get_contents($file ?? $this->file);

        if (false === $content) {
            return new IngressEventList();
        }

        return $this->parse($content) ?? new IngressEventList();
    }

    /** The list of a file's content, null if it is not one. */
    public function parse(string $content): ?IngressEventList
    {
        try {
            /** @var array{generatedAt?: string, events?: list<array{type: string, title: string, start: string, end: string}>} $data */
            $data = \json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

            return new IngressEventList(
                \array_map(IngressEvent::fromArray(...), $data['events'] ?? []),
                $data['generatedAt'] ?? null,
            );
        } catch (\JsonException|\TypeError|\ValueError) {
            return null;
        }
    }

    /**
     * Writes the list, if it changed: a run that found nothing new leaves no diff.
     *
     * @param list<IngressEvent> $events
     *
     * @return bool whether the file changed
     */
    public function save(array $events, string $generatedAt, ?string $file = null): bool
    {
        $file ??= $this->file;

        if ($this->load($file)->events == $events && [] !== $events) {
            return false;
        }

        new Filesystem()->dumpFile($file, \json_encode([
            'generatedAt' => $generatedAt,
            'events' => \array_map(static fn (IngressEvent $event): array => $event->toArray(), $events),
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");

        return true;
    }
}
