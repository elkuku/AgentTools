<?php

declare(strict_types=1);

namespace App\Twig;

use App\Timeline\TimelineBuilder;
use App\Timeline\TimelineEntry;
use Twig\Attribute\AsTwigFunction;

final readonly class TimelineExtension
{
    /**
     * The years of a timeline, newest first, for its year filter.
     *
     * @param list<TimelineEntry> $timeline
     *
     * @return list<int>
     */
    #[AsTwigFunction('timeline_years')]
    public function years(array $timeline): array
    {
        return TimelineBuilder::years($timeline);
    }
}
