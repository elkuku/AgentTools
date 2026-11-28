<?php

declare(strict_types=1);

namespace App\History;

use App\IngressEvents\EventMedals;
use App\IngressEvents\EventType;
use App\IngressEvents\IngressEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Where the history documents (docs/events/) fall behind the Ingress events of app:events:fetch:
 * a Mission Day or an XM Anomaly site of the list that is not in its document, a row still
 * marked "(upcoming)" after its day, and an event over that gave a medal of its own (ours or
 * ingress.plus') whose name is in neither operations.md nor ingress-years.md. The badges
 * workflow turns them into an issue.
 */
final readonly class HistoryCheck
{
    /** The documents an event of a type goes to, by event type. */
    private const array DOCUMENTS = ['mission_day' => 'mission-days', 'anomaly' => 'anomalies'];

    /** The documents of the events with a medal of their own. */
    private const array MEDAL_DOCUMENTS = ['operations', 'ingress-years'];

    public function __construct(
        private HistoryLoader $loader,
        private EventMedals $medals,
        #[Autowire('%kernel.project_dir%/../docs/events')]
        private string $directory,
    ) {
    }

    /**
     * @param list<IngressEvent> $events
     * @param string             $today  Y-m-d
     *
     * @return array{missing: list<string>, upcoming: list<string>, medals: list<string>} each as a line of the issue
     */
    public function check(array $events, string $today): array
    {
        $rows = [];
        $upcoming = [];

        foreach (self::DOCUMENTS as $slug) {
            foreach ($this->rows($slug) as [$attendance, $note]) {
                $rows[$slug][$attendance->date.'|'.self::key($attendance->text)] = true;

                if (\str_contains($note, 'upcoming') && $attendance->date < $today) {
                    $upcoming[] = \sprintf('%s: %s, %s, still "(upcoming)"', $slug.'.md', $attendance->date, $attendance->text);
                }
            }
        }

        return ['missing' => $this->notListed($events, $rows), 'upcoming' => $upcoming, 'medals' => $this->medalsMissing($events, $today)];
    }

    /**
     * The Mission Days and Anomaly sites of the list that their document does not have.
     *
     * @param list<IngressEvent>                 $events
     * @param array<string, array<string, true>> $rows   by document, by date|key
     *
     * @return list<string>
     */
    private function notListed(array $events, array $rows): array
    {
        $missing = [];

        foreach ($events as $event) {
            $slug = self::DOCUMENTS[$event->type->value] ?? null;

            if (null !== $slug && null !== $event->location && !$this->listed($rows[$slug] ?? [], $event)) {
                $missing[] = \sprintf('%s: %s, %s (%s)', $slug.'.md', $event->start, $event->location, $event->title);
            }
        }

        return $missing;
    }

    /**
     * The table rows that can be attended, with their third column (the note).
     *
     * @return list<array{HistoryAttendance, string}>
     */
    private function rows(string $slug): array
    {
        $rows = [];

        foreach ($this->loader->load($slug)->blocks as $block) {
            foreach ($block->attendance as $index => $attendance) {
                if (null !== $attendance) {
                    $note = \implode('', \array_map(static fn (HistoryInline $inline): string => $inline->text, $block->rows[$index][2] ?? []));
                    $rows[] = [$attendance, $note];
                }
            }
        }

        return $rows;
    }

    /**
     * A row of the event's day whose place starts like its location: "Zhuhai, China" is "Zhuhai".
     *
     * @param array<string, true> $rows by date|key
     */
    private function listed(array $rows, IngressEvent $event): bool
    {
        $city = $this->key(\explode(',', (string) $event->location)[0]);

        foreach (\array_keys($rows) as $row) {
            [$date, $text] = \explode('|', $row, 2);

            if ($date === $event->start && \str_contains($text, $city)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The ended events whose medal of their own no medal document names, each medal once.
     *
     * @param list<IngressEvent> $events
     *
     * @return list<string>
     */
    private function medalsMissing(array $events, string $today): array
    {
        $documents = $this->key(\implode(' ', \array_map(fn (string $slug): string => (string) @\file_get_contents($this->directory.'/'.$slug.'.md'), self::MEDAL_DOCUMENTS)));
        $missing = [];

        foreach ($events as $event) {
            $recurring = \in_array($event->type, [EventType::FirstSaturday, EventType::SecondSunday, EventType::MissionDay, EventType::Anomaly], true);
            $name = $this->medals->of($event)['name'] ?? $event->medal;

            if ($recurring || null === $name || $event->end >= $today || \str_contains($documents, $this->key($name))) {
                continue;
            }

            $missing[$name] = \sprintf('%s (%s, %s to %s)', $name, $event->title, $event->start, $event->end);
        }

        return \array_values($missing);
    }

    /** Lower case letters and digits, as words: for comparing names and places. */
    private function key(string $text): string
    {
        return \trim((string) \preg_replace('/[^\p{L}\p{N}]+/u', ' ', \mb_strtolower($text)));
    }
}
