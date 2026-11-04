<?php

declare(strict_types=1);

namespace App\Command;

use App\IngressEvents\IngressEventCollector;
use App\IngressEvents\IngressEventFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gathers the Ingress events from ingress.com/news into data/ingress-events.json.
 */
#[AsCommand(name: 'app:events:fetch', description: 'Gather the Ingress events from ingress.com/news into data/ingress-events.json')]
final readonly class FetchEventsCommand
{
    public function __construct(
        private IngressEventCollector $collector,
        private IngressEventFile $file,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Write to this file instead')]
        ?string $output = null,
        #[Option(description: 'The first year to gather; default this year, with the next')]
        ?int $year = null,
    ): int {
        $firstYear = $year ?? (int) new \DateTimeImmutable()->format('Y');

        try {
            $result = $this->collector->collect([$firstYear, $firstYear + 1]);
        } catch (\Throwable $throwable) {
            $io->error('The events could not be gathered, the file is unchanged: '.$throwable->getMessage());

            return Command::FAILURE;
        }

        if ([] === $result->events) {
            $io->error('No events found, the file is unchanged: did ingress.com change its pages?');

            return Command::FAILURE;
        }

        foreach ($result->skipped as $entry) {
            $io->warning('No usable date: '.$entry);
        }

        $changed = $this->file->save($result->events, new \DateTimeImmutable()->format(DATE_ATOM), $output);

        $io->success(\sprintf(
            '%d events from %d pages: %s %s.',
            \count($result->events),
            \count($result->sources),
            $output ?? $this->file->path(),
            $changed ? 'updated' : 'already up to date',
        ));

        return Command::SUCCESS;
    }
}
