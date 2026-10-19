<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AgentStatInput;
use App\Exception\InvalidStatsException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Parses Ingress all-time stats as copied from the game (a tab-separated header line and one
 * data line) into an AgentStatInput, like 4e-website's CsvParser. The app does the same
 * conversion before POST /api/agent-stats (upload-app: data/IngressStats.kt).
 */
final readonly class IngressStatsParser
{
    /** "Time Span" values of all-time stats, per game language. */
    private const array ALL_TIME_SPANS = ['ALL TIME', 'GESAMT', 'SIEMPRE'];

    private const array TEXT_COLUMNS = ['nickname', 'faction'];

    /**
     * Ingress stats header → agent_stat column (4e-website's MedalChecker::$primeHeaders).
     * Headers not listed here are not stored.
     */
    public const array COLUMNS = [
        'Agent Name' => 'nickname',
        'Agent Faction' => 'faction',
        'Lifetime AP' => 'ap',
        'Unique Portals Visited' => 'explorer',
        'Portals Discovered' => 'portals_discovered',
        'Seer Points' => 'seer',
        'OPR Agreements' => 'recon',
        'Distance Walked' => 'trekker',
        'Resonators Deployed' => 'builder',
        'Links Created' => 'connector',
        'Control Fields Created' => 'mind_controller',
        'Mind Units Captured' => 'illuminator',
        'Longest Link Ever Created' => 'longest_link',
        'Largest Control Field' => 'largest_field',
        'XM Recharged' => 'recharger',
        'Portals Captured' => 'liberator',
        'Unique Portals Captured' => 'pioneer',
        'Mods Deployed' => 'engineer',
        'Resonators Destroyed' => 'purifier',
        'Unique Missions Completed' => 'specops',
        'Hacks' => 'hacker',
        'Glyph Hack Points' => 'translator',
        // Old and new name
        'Longest Hacking Streak' => 'sojourner',
        'Longest Sojourner Streak' => 'sojourner',
        'Completed Hackstreaks' => 'epoch',
        // Old and new name
        'Agents Successfully Recruited' => 'recruiter',
        'Agents Recruited' => 'recruiter',
        'Mission Day(s) Attended' => 'missionday',
        'NL-1331 Meetup(s) Attended' => 'nl1331_meetups',
        'First Saturday Events' => 'ifs',
        'Second Sunday Events' => 'second_sunday',
        'Portal Scans Uploaded' => 'scout',
        // Old and new name
        'Scout Controller on Unique Portals' => 'scout_controller',
        'Uniques Scout Controlled' => 'scout_controller',
        'Drone Hacks' => 'maverick',
        'Unique Portals Drone Visited' => 'drone_portals_visited',
        'Furthest Drone Distance' => 'drone_flight_distance',
        'Forced Drone Recalls' => 'drone_forced_recalls',
        'Drones Returned' => 'drones_returned',
        'Level' => 'level',
        'Recursions' => 'recursions',
        'Kinetic Capsules Completed' => 'kinetic_capsules_completed',
        'Months Subscribed' => 'months_subscribed',
        // Events recorded by 4e-website
        'Umbra: Unique Resonator Slots Deployed' => 'current_challenge',
        'Didact Fields Created' => 'current_challenge',
        'Operation Chronos Points' => 'current_challenge',
        'Machina Portals Reclaimed' => 'reclaimer',
    ];

    public function __construct(
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws InvalidStatsException
     */
    public function parse(string $text): AgentStatInput
    {
        $row = $this->parseRow($text);

        $timeSpan = $row['Time Span'] ?? '';
        if (!\in_array($timeSpan, self::ALL_TIME_SPANS, true)) {
            throw new InvalidStatsException([\sprintf('Only all-time stats can be imported, not "%s".', $timeSpan)]);
        }

        return $this->createInput($this->toColumns($row));
    }

    /**
     * @param array<string, string> $row
     *
     * @return array<string, string|int> agent_stat column => value
     *
     * @throws InvalidStatsException
     */
    private function toColumns(array $row): array
    {
        $values = ['datetime' => \trim(($row['Date (yyyy-mm-dd)'] ?? '').' '.($row['Time (hh:mm:ss)'] ?? ''))];

        foreach ($row as $name => $value) {
            $column = self::COLUMNS[$name] ?? null;
            if (null !== $column && '' !== $value) {
                $values[$column] = $this->toValue($column, $name, $value);
            }
        }

        return $values;
    }

    /**
     * @throws InvalidStatsException
     */
    private function toValue(string $column, string $name, string $value): string|int
    {
        if (\in_array($column, self::TEXT_COLUMNS, true)) {
            return $value;
        }

        if (1 !== \preg_match('/^\d+$/', $value)) {
            throw new InvalidStatsException([\sprintf('"%s" is not a number: "%s".', $name, $value)]);
        }

        return (int) $value;
    }

    /**
     * @param array<string, string|int> $values
     *
     * @throws InvalidStatsException
     */
    private function createInput(array $values): AgentStatInput
    {
        try {
            $input = $this->denormalizer->denormalize($values, AgentStatInput::class);
        } catch (ExceptionInterface $exception) {
            throw new InvalidStatsException([$exception->getMessage()]);
        }

        $errors = [];
        foreach ($this->validator->validate($input) as $violation) {
            $errors[] = $violation->getPropertyPath().': '.$violation->getMessage();
        }

        if ([] !== $errors) {
            throw new InvalidStatsException($errors);
        }

        return $input;
    }

    /**
     * @return array<string, string> header => cell of the single data line
     *
     * @throws InvalidStatsException
     */
    private function parseRow(string $text): array
    {
        $lines = \array_values(\array_filter(
            \preg_split('/\R/', $text) ?: [],
            static fn (string $line): bool => '' !== \trim($line),
        ));

        if (\count($lines) < 2 || !\str_contains($lines[0], "\t")) {
            throw new InvalidStatsException(['Paste the stats as copied from Ingress: a header line and a data line, separated by tabs.']);
        }

        if (2 !== \count($lines)) {
            throw new InvalidStatsException(['Paste exactly one line of stats.']);
        }

        $header = $this->cells($lines[0]);
        $cells = $this->cells($lines[1]);

        if (\count($cells) > \count($header)) {
            throw new InvalidStatsException(['The data line has more fields than the header line.']);
        }

        return \array_combine($header, \array_pad($cells, \count($header), ''));
    }

    /**
     * Ingress ends every line with a tab: trailing empty cells are dropped.
     *
     * @return list<string>
     */
    private function cells(string $line): array
    {
        $cells = \array_map(trim(...), \explode("\t", $line));
        while ([] !== $cells && '' === \end($cells)) {
            \array_pop($cells);
        }

        return $cells;
    }
}
