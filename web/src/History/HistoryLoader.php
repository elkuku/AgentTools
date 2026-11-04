<?php

declare(strict_types=1);

namespace App\History;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The documents of docs/events/ (the deploy has the whole repository), parsed once per change of
 * the file. Without a file its document is empty.
 */
final readonly class HistoryLoader
{
    /** The documents, as their file names, in the order of the page's tabs. */
    public const array SLUGS = ['anomalies', 'mission-days'];

    public function __construct(
        private HistoryParser $parser,
        private HistoryAttendanceResolver $attendance,
        private CacheInterface $cache,
        #[Autowire('%kernel.project_dir%/../docs/events')]
        private string $directory,
    ) {
    }

    public function load(string $slug): HistoryDocument
    {
        $file = $this->directory.'/'.$slug.'.md';
        $modified = @\filemtime($file);

        if (false === $modified) {
            return new HistoryDocument();
        }

        return $this->cache->get(
            'history_'.\hash('xxh3', $file.'|'.$modified.'|'.\filesize($file)),
            fn (): HistoryDocument => $this->attendance->resolve($this->parser->parse((string) \file_get_contents($file)), $slug),
        );
    }
}
