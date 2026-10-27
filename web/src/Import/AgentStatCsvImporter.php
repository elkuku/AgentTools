<?php

declare(strict_types=1);

namespace App\Import;

use App\Dto\AgentStatInput;
use App\Entity\User;
use App\Exception\AgentNameException;
use App\Exception\StatsAlreadyAddedException;
use App\Service\AgentStatService;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports agent stats from a CSV file with a header row of agent_stat column names, as
 * 4e-website exports them (Migrate → Export CSV). Each row is saved like a POST /api/agent-stats
 * (AgentStatInput, validated, AgentStatService): stats already imported are skipped, invalid
 * rows and stats of another agent are reported. Columns AgentTools does not know are ignored;
 * rows without nickname or faction (4e-website's before May 2020) get them from the file.
 */
final readonly class AgentStatCsvImporter
{
    /** Stay text: the agent, the faction and the datetime. */
    private const array TEXT_COLUMNS = ['datetime', 'nickname', 'faction'];

    public function __construct(
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
        private AgentStatService $agentStatService,
    ) {
    }

    /**
     * @param resource $csv
     */
    public function import(User $owner, $csv): CsvImportResult
    {
        $result = new CsvImportResult();

        $header = \fgetcsv($csv, escape: '');
        if (false === $header || [] === \array_filter($header, static fn (?string $column): bool => null !== $column && '' !== \trim($column))) {
            $result->errors[] = '1: no header row';

            return $result;
        }

        $columns = \array_map(static fn (?string $column): string => \trim((string) $column), $header);
        $rows = [];
        $line = 1;
        while (false !== ($row = \fgetcsv($csv, escape: ''))) {
            ++$line;
            // Empty lines
            if ([null] !== $row) {
                $rows[$line] = $row;
            }
        }

        $defaults = $this->defaults($owner, $columns, $rows);
        foreach ($rows as $number => $row) {
            $this->importRow($owner, $columns, $row, $number, $defaults, $result);
        }

        return $result;
    }

    /**
     * For rows without them: 4e-website's rows before May 2020 have no nickname and faction. The
     * owner's agent name (else the file's first nickname) and the file's first faction.
     *
     * @param list<string>                  $columns
     * @param array<int, list<string|null>> $rows
     *
     * @return array{nickname: string, faction: string}
     */
    private function defaults(User $owner, array $columns, array $rows): array
    {
        $first = static function (string $column) use ($columns, $rows): string {
            $index = \array_search($column, $columns, true);
            foreach ($rows as $row) {
                $value = false === $index ? '' : \trim((string) ($row[$index] ?? ''));
                if ('' !== $value) {
                    return $value;
                }
            }

            return '';
        };

        return ['nickname' => $owner->getAgentName() ?? $first('nickname'), 'faction' => $first('faction')];
    }

    /**
     * @param list<string>                             $columns
     * @param list<string|null>                        $row
     * @param array{nickname: string, faction: string} $defaults
     */
    private function importRow(User $owner, array $columns, array $row, int $line, array $defaults, CsvImportResult $result): void
    {
        if (\count($row) !== \count($columns)) {
            $result->errors[] = \sprintf('%d: %d values for %d columns', $line, \count($row), \count($columns));

            return;
        }

        try {
            $values = $this->values(\array_combine($columns, $row));
            foreach ($defaults as $column => $default) {
                if ('' === ($values[$column] ?? '')) {
                    $values[$column] = $default;
                }
            }

            $input = $this->denormalizer->denormalize($values, AgentStatInput::class);
        } catch (NotNormalizableValueException $notNormalizableValueException) {
            // E.g. text for a stat
            $result->errors[] = \sprintf('%d: %s: %s', $line, $notNormalizableValueException->getPath() ?? '', $notNormalizableValueException->getMessage());

            return;
        }

        $violations = $this->validator->validate($input);
        if (\count($violations) > 0) {
            $result->errors[] = $line.': '.\implode(' ', \array_map(
                static fn (ConstraintViolationInterface $violation): string => \trim($violation->getPropertyPath().': '.$violation->getMessage(), ': '),
                \iterator_to_array($violations),
            ));

            return;
        }

        try {
            $this->agentStatService->create($owner, $input);
            ++$result->imported;
        } catch (StatsAlreadyAddedException) {
            ++$result->skipped;
        } catch (AgentNameException $agentNameException) {
            $result->errors[] = $line.': '.$agentNameException->getMessage();
        }
    }

    /**
     * The values as AgentStatInput takes them: numbers for the stats, null for empty ones.
     *
     * @param array<string, string|null> $row
     *
     * @return array<string, int|string|null>
     */
    private function values(array $row): array
    {
        $values = [];
        foreach ($row as $column => $value) {
            $value = \trim((string) $value);
            $values[$column] = match (true) {
                \in_array($column, self::TEXT_COLUMNS, true) => $value,
                '' === $value => null,
                // Not a number: left as it is, the denormalizer reports it
                1 === \preg_match('/^\d+$/', $value) => (int) $value,
                default => $value,
            };
        }

        return $values;
    }
}
