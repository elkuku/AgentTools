<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * On a TimelineEvent: a travel (an event with an end date) must not share a day with another
 * travel of its owner, so that the timeline has at most one travel at a time.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NoOverlappingTravel extends Constraint
{
    public string $message = 'Travels cannot overlap: {{ travel }}.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
