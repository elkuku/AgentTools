<?php

declare(strict_types=1);

namespace App\Command;

use App\History\HistoryCheck;
use App\IngressEvents\IngressEventSource;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists where the history documents fall behind the Ingress events (HistoryCheck), as the text of
 * an issue with --output (no file when nothing is), for .github/workflows/badges.yml.
 */
#[AsCommand(name: 'app:history:check', description: 'List what the history documents (docs/events/) miss of the Ingress events')]
final readonly class HistoryCheckCommand
{
    public function __construct(
        private HistoryCheck $check,
        private IngressEventSource $source,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Write the findings as the text of an issue to this file, none when there are none')]
        ?string $output = null,
        #[Option(description: 'The current day (Y-m-d)')]
        ?string $today = null,
    ): int {
        $found = $this->check->check($this->source->load()->events, $today ?? new \DateTimeImmutable()->format('Y-m-d'));
        $sections = [
            'missing' => 'Events of the list that are not in their history document:',
            'upcoming' => 'Rows still marked as upcoming after their day:',
            'medals' => 'Medals of past events in neither operations.md nor ingress-years.md:',
        ];
        $text = '';

        foreach ($sections as $key => $title) {
            if ([] !== $found[$key]) {
                $text .= $title."\n\n".\implode("\n", \array_map(static fn (string $line): string => '- '.$line, $found[$key]))."\n\n";
                $io->section($title);
                $io->listing($found[$key]);
            }
        }

        if ('' === $text) {
            $io->success('The history documents have every event of the list.');

            return Command::SUCCESS;
        }

        if (null !== $output) {
            \file_put_contents($output, $text.'Update the documents in docs/events/ (the steps are in the developer guide). This issue closes on the next run without findings.'."\n");
        }

        return Command::SUCCESS;
    }
}
