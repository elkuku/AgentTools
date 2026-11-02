<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Imports a CSV file of agent stats in parts, one request each (stats_import_controller.js), so a
 * big file does not run into the server's time limit: start() keeps the file in var/imports/{env} and
 * the progress in the session, each next() imports the next BATCH rows.
 */
final readonly class CsvImportJob
{
    /** Rows per request. */
    public const int BATCH = 100;

    /** Files of imports that were not finished are removed after this many seconds. */
    private const int MAX_AGE = 86400;

    private const string SESSION_KEY = 'csv_import.';

    public function __construct(
        private AgentStatCsvImporter $importer,
        private RequestStack $requestStack,
        private Filesystem $filesystem,
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
    ) {
    }

    /** var/imports/{env}: the tests' files apart from dev's. */
    private function directory(): string
    {
        return $this->projectDir.'/var/imports/'.$this->environment;
    }

    /**
     * Keeps the file for the import, nothing imported yet.
     *
     * @return array{id: string, rows: int} the import's id and the file's rows
     */
    public function start(User $owner, UploadedFile $file): array
    {
        $this->removeOld();

        $id = \bin2hex(\random_bytes(16));
        $file->move($this->directory(), $id.'.csv');
        // Nothing imported: only the rows counted
        $rows = $this->run($owner, $id, 0, 0)->rows;

        $this->requestStack->getSession()->set(self::SESSION_KEY.$id, ['owner' => $owner->id, 'offset' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => []]);

        return ['id' => $id, 'rows' => $rows];
    }

    /**
     * Imports the next rows of the import $id; null for an import that is not the owner's (or is
     * done). The result has the totals so far; done once every row is imported, then the file is
     * removed.
     *
     * @return array{result: CsvImportResult, done: int, finished: bool}|null done: the rows imported so far
     */
    public function next(User $owner, string $id): ?array
    {
        $session = $this->requestStack->getSession();
        /** @var array{owner: int, offset: int, imported: int, skipped: int, errors: list<string>}|null $state */
        $state = $session->get(self::SESSION_KEY.$id);
        if (null === $state || $state['owner'] !== $owner->id || !\is_file($this->path($id))) {
            return null;
        }

        $part = $this->run($owner, $id, $state['offset'], self::BATCH);

        $result = new CsvImportResult();
        $result->rows = $part->rows;
        $result->imported = $state['imported'] + $part->imported;
        $result->skipped = $state['skipped'] + $part->skipped;
        $result->errors = [...$state['errors'], ...$part->errors];

        $done = \min($part->rows, $state['offset'] + self::BATCH);
        $finished = $done >= $part->rows;

        if ($finished) {
            $session->remove(self::SESSION_KEY.$id);
            $this->filesystem->remove($this->path($id));
        } else {
            $session->set(self::SESSION_KEY.$id, ['owner' => $owner->id, 'offset' => $done, 'imported' => $result->imported, 'skipped' => $result->skipped, 'errors' => $result->errors]);
        }

        return ['result' => $result, 'done' => $done, 'finished' => $finished];
    }

    private function run(User $owner, string $id, int $offset, int $limit): CsvImportResult
    {
        $csv = \fopen($this->path($id), 'r');
        if (false === $csv) {
            throw new \RuntimeException('The uploaded file cannot be read.');
        }

        try {
            return $this->importer->import($owner, $csv, $offset, $limit);
        } finally {
            \fclose($csv);
        }
    }

    private function path(string $id): string
    {
        // Only the ids start() makes
        if (1 !== \preg_match('/^[0-9a-f]{32}$/', $id)) {
            return $this->directory().'/invalid';
        }

        return $this->directory().'/'.$id.'.csv';
    }

    /** The files of imports that were left unfinished. */
    private function removeOld(): void
    {
        foreach (\glob($this->directory().'/*.csv') ?: [] as $file) {
            if (\filemtime($file) < \time() - self::MAX_AGE) {
                $this->filesystem->remove($file);
            }
        }
    }
}
