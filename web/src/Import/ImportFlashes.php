<?php

declare(strict_types=1);

namespace App\Import;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The result of an import as flash messages on the user's stats: what was imported and skipped,
 * and the first rows that were not imported.
 */
final readonly class ImportFlashes
{
    /** At most this many rows that were not imported are listed. */
    private const int SHOWN_ERRORS = 10;

    public function __construct(
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {
    }

    public function stats(CsvImportResult $result): void
    {
        $this->add($result->imported > 0 ? 'success' : 'info', $this->translator->trans('stats_import.done', [
            '%imported%' => $result->imported,
            '%skipped%' => $result->skipped,
        ]));
        $this->errors($result->errors);
    }

    public function medals(CustomMedalsImportResult $result): void
    {
        $this->add($result->added + $result->upgraded > 0 ? 'success' : 'info', $this->translator->trans('stats_import.medals_done', [
            '%added%' => $result->added,
            '%upgraded%' => $result->upgraded,
            '%kept%' => $result->kept,
        ]));
        $this->errors($result->errors);
    }

    /**
     * @param list<string> $errors what was not imported
     */
    private function errors(array $errors): void
    {
        if ([] === $errors) {
            return;
        }

        // The texts come from the file: escaped, flashes are shown as HTML
        $lines = \array_map(\htmlspecialchars(...), \array_slice($errors, 0, self::SHOWN_ERRORS));
        $this->add('warning', $this->translator->trans('stats_import.errors', ['%count%' => \count($errors)])
            .'<ul class="mb-0"><li>'.\implode('</li><li>', $lines).'</li></ul>');
    }

    private function add(string $type, string $message): void
    {
        $session = $this->requestStack->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
