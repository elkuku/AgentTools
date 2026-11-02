<?php

declare(strict_types=1);

namespace App\Import;

/**
 * What a CSV import of agent stats did.
 */
final class CsvImportResult
{
    /** The file's rows with values (without the header and empty lines). */
    public int $rows = 0;

    public int $imported = 0;

    /** Stats with a datetime that was already imported. */
    public int $skipped = 0;

    /** @var list<string> "line: message" for rows that were not imported */
    public array $errors = [];
}
