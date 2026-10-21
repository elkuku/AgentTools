<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\State\CustomMedalsProcessor;
use App\State\CustomMedalsProvider;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * GET / PUT /api/me/custom-medals: the signed-in user's custom medals, as 4e-website stores them.
 * The app recognizes them in screenshots; PUT replaces all of them.
 */
#[ApiResource(
    description: 'Your custom medals (anomaly, annual and event medals) as 4e-website stores them: medal keys of its MedalChecker::$customMedals with the tier (anomaly, event; "" for a medal without tiers) or the level 1 (bronze) to 5 (onyx) (annual). PUT replaces all of them.',
    operations: [
        new Get(uriTemplate: '/me/custom-medals', security: "is_granted('ROLE_USER')", provider: CustomMedalsProvider::class),
        new Put(
            uriTemplate: '/me/custom-medals',
            collectDenormalizationErrors: true,
            security: "is_granted('ROLE_USER')",
            read: false,
            processor: CustomMedalsProcessor::class,
        ),
    ],
)]
final class CustomMedals
{
    /** Medal and tier keys of 4e-website, e.g. plus_gamma_onsite, 2026_summer_solstice, platinum. */
    private const string KEY_PATTERN = '/^[a-z0-9_]{1,50}$/';

    private const array GROUPS = ['anomaly', 'annual', 'event'];

    /**
     * @param array<string, string> $anomaly medal => tier ("" for a medal without tiers)
     * @param array<string, int>    $annual  medal => level 1 (bronze) to 5 (onyx)
     * @param array<string, string> $event   medal => tier ("" for a medal without tiers)
     */
    public function __construct(
        public array $anomaly = [],
        public array $annual = [],
        public array $event = [],
    ) {
    }

    /**
     * @param array<string, array<string, int|string>> $medals as stored on the User
     */
    public static function fromArray(array $medals): self
    {
        /** @var array<string, string> $anomaly */
        $anomaly = $medals['anomaly'] ?? [];
        /** @var array<string, int> $annual */
        $annual = $medals['annual'] ?? [];
        /** @var array<string, string> $event */
        $event = $medals['event'] ?? [];

        return new self($anomaly, $annual, $event);
    }

    /**
     * @return array<string, array<string, int|string>> for the User, empty groups left out
     */
    public function toArray(): array
    {
        return \array_filter(
            ['anomaly' => $this->anomaly, 'annual' => $this->annual, 'event' => $this->event],
            static fn (array $medals): bool => [] !== $medals,
        );
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        foreach (self::GROUPS as $group) {
            foreach ($this->{$group} as $medal => $value) {
                $error = $this->error($group, (string) $medal, $value);
                if (null !== $error) {
                    $context->buildViolation($error[1])->atPath($error[0])->addViolation();
                }
            }
        }
    }

    /**
     * @return array{string, string}|null the property path and the message, null if valid
     */
    private function error(string $group, string $medal, mixed $value): ?array
    {
        if (!$this->isKey($medal)) {
            return [$group, 'Not a medal key.'];
        }

        $path = $group.'['.$medal.']';

        if ('annual' === $group) {
            return \is_int($value) && $value >= 1 && $value <= 5 ? null : [$path, 'The level must be 1 to 5.'];
        }

        return \is_string($value) && ('' === $value || $this->isKey($value)) ? null : [$path, 'Not a tier.'];
    }

    private function isKey(string $key): bool
    {
        return 1 === \preg_match(self::KEY_PATTERN, $key);
    }
}
