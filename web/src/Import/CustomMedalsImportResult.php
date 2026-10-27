<?php

declare(strict_types=1);

namespace App\Import;

/**
 * What an import of custom medals did.
 */
final class CustomMedalsImportResult
{
    /** Medals the user did not have. */
    public int $added = 0;

    /** Medals at a higher tier than the user's. */
    public int $upgraded = 0;

    /** Medals the user already had at the same or a higher tier. */
    public int $kept = 0;

    /** @var list<string> what was not imported */
    public array $errors = [];
}
