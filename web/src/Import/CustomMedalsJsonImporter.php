<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\User;
use App\Medal\CustomMedal;
use App\Medal\MedalChecker;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Imports custom medals from a JSON file as 4e-website exports them (Migrate → Export custom
 * medals): {"anomaly": {"orion": "platinum"}, "annual": {"voyager": 5}, "event": {…}}. They are
 * merged with the user's: a medal the user has keeps the higher tier, as the app merges them.
 * Medals or tiers AgentTools does not know are reported.
 */
final readonly class CustomMedalsJsonImporter
{
    public function __construct(
        private MedalChecker $medalChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function import(User $owner, string $json): CustomMedalsImportResult
    {
        $result = new CustomMedalsImportResult();
        $file = $this->decode($json, $result);
        if (null === $file) {
            return $result;
        }

        $known = $this->known();
        $medals = $owner->getCustomMedals();
        foreach ($this->entries($file) as [$group, $key, $value]) {
            $this->importMedal($known[$group.'/'.$key] ?? null, $group, $key, $value, $medals, $result);
        }

        $owner->setCustomMedals($medals);
        $this->entityManager->flush();

        return $result;
    }

    /**
     * @return array<string, CustomMedal> by "group/key"
     */
    private function known(): array
    {
        $known = [];
        foreach ($this->medalChecker->customMedals() as $medal) {
            $known[$medal->group.'/'.$medal->key] = $medal;
        }

        return $known;
    }

    /**
     * Merges one medal of the file ([$medal] null for one AgentTools does not know).
     *
     * @param array<string, array<string, int|string>> $medals the user's, changed
     */
    private function importMedal(?CustomMedal $medal, string $group, string $key, mixed $value, array &$medals, CustomMedalsImportResult $result): void
    {
        $rank = $medal instanceof CustomMedal ? $this->rank($medal, $value) : null;
        if (!$medal instanceof CustomMedal || null === $rank) {
            $result->errors[] = \sprintf('%s: %s %s', $group, $key, \is_scalar($value) ? (string) $value : '');

            return;
        }

        $this->merge($medals, $medal, $value, $rank, $result);
    }

    /**
     * The file's groups, null (and why in [$result]) for no JSON object.
     *
     * @return array<mixed>|null
     */
    private function decode(string $json, CustomMedalsImportResult $result): ?array
    {
        try {
            $file = \json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            $result->errors[] = 'Not JSON: '.$jsonException->getMessage();

            return null;
        }

        if (!\is_array($file)) {
            $result->errors[] = 'Not custom medals: an object of groups is expected.';

            return null;
        }

        return $file;
    }

    /**
     * Every medal of every group in the file.
     *
     * @param array<mixed> $file
     *
     * @return \Generator<array{string, string, mixed}> group, medal key, tier or level
     */
    private function entries(array $file): \Generator
    {
        foreach ($file as $group => $values) {
            foreach (\is_array($values) ? $values : [] as $key => $value) {
                yield [(string) $group, (string) $key, $value];
            }
        }
    }

    /**
     * How high [$value] is for [$medal] (a tier's position, an annual medal's level), null if it
     * is none of its tiers.
     */
    private function rank(CustomMedal $medal, mixed $value): ?int
    {
        if ('annual' === $medal->group) {
            return \is_int($value) && $value >= 1 && $value <= 5 ? $value : null;
        }

        if ([] === $medal->tiers) {
            return '' === $value ? 0 : null;
        }

        $index = \array_search($value, $medal->tiers, true);

        return false === $index ? null : $index;
    }

    /**
     * @param array<string, array<string, int|string>> $medals the user's, changed
     */
    private function merge(array &$medals, CustomMedal $medal, mixed $value, int $rank, CustomMedalsImportResult $result): void
    {
        $saved = $medals[$medal->group][$medal->key] ?? null;
        if (null === $saved) {
            ++$result->added;
        } elseif ($rank > ($this->rank($medal, $saved) ?? -1)) {
            ++$result->upgraded;
        } else {
            ++$result->kept;

            return;
        }

        $medals[$medal->group][$medal->key] = \is_int($value) ? $value : (string) $value;
    }
}
