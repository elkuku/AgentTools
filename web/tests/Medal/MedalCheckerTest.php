<?php

declare(strict_types=1);

namespace App\Tests\Medal;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\User;
use App\Medal\MedalChecker;
use App\Medal\MedalLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MedalCheckerTest extends TestCase
{
    /** Where asset_mapper.yaml takes the images from. */
    private const array IMAGE_DIRS = [
        'badges/' => __DIR__.'/../../../app/app/src/main/res/drawable-nodpi/',
        'custom_medals/' => __DIR__.'/../../../app/app/src/main/assets/custom_medals/',
    ];

    private MedalChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new MedalChecker();
    }

    public function testLevelsAndOnyxCount(): void
    {
        $explorer = $this->checker->medals()[0];

        self::assertSame(0, $explorer->level(null));
        self::assertSame(0, $explorer->level(99));
        self::assertSame(1, $explorer->level(100));
        self::assertSame(5, $explorer->level(30000));
        self::assertSame(0, $explorer->onyxCount(29999));
        self::assertSame(2, $explorer->onyxCount(60001));
    }

    public function testMedalLevelsHighestFirstWithoutUnearnedDiscontinuedMedals(): void
    {
        $levels = $this->checker->medalLevels($this->stat(explorer: 30000, recon: 750, seer: null));
        $columns = \array_map(static fn (MedalLevel $level): string => $level->medal->column, $levels);

        self::assertSame(['explorer', 'recon'], \array_slice($columns, 0, 2));
        self::assertNotContains('seer', $columns);
        self::assertSame('badges/badge_explorer_black.webp', $levels[0]->badge());
        self::assertSame('Onyx', $levels[0]->levelName());
        // recon's images are named opr
        self::assertSame('badges/badge_opr_silver.webp', $levels[1]->badge());
        self::assertNull($levels[2]->badge());

        $withSeer = $this->checker->medalLevels($this->stat(explorer: 0, recon: 0, seer: 10));
        self::assertContains('seer', \array_map(static fn (MedalLevel $level): string => $level->medal->column, $withSeer));
    }

    public function testCoreBadges(): void
    {
        self::assertSame([], $this->checker->coreBadges(0));
        self::assertSame(['badges/unique_badge_core.webp'], $this->checker->coreBadges(1));
        self::assertCount(3, $this->checker->coreBadges(36));
        self::assertCount(5, $this->checker->coreBadges(69));
    }

    public function testCustomMedalTiersIn4eOrder(): void
    {
        $tiers = $this->checker->customMedalTiers([
            'event' => ['csans' => 'gold'],
            'annual' => ['voyager' => 5, 'sage' => 9],
            'anomaly' => ['echo' => '', 'orion' => 'platinum', 'no_such_medal' => 'gold'],
        ]);

        self::assertSame(['anomaly', 'annual', 'event'], \array_keys($tiers));
        // orion comes before echo in 4e's list
        self::assertSame(['Orion (Platinum)', 'Echo'], \array_map(static fn ($tier): string => $tier->label(), $tiers['anomaly']));
        // An annual level outside 1 to 5 is left out
        self::assertCount(1, $tiers['annual']);
        self::assertSame('custom_medals/badge_voyager_black.webp', $tiers['annual'][0]->image());
        self::assertSame('custom_medals/event_badge_csans_gold.webp', $tiers['event'][0]->image());
        self::assertSame([], $this->checker->customMedalTiers([]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function imageProvider(): iterable
    {
        $checker = new MedalChecker();
        $images = [...\array_values(MedalChecker::CORE_BADGES), 'badges/unique_badge_simulacrum.webp', 'badges/ico_padlock.webp', 'badges/ico_drone.webp', 'badges/ico_kinetic_capsule.webp'];

        foreach ($checker->medals() as $medal) {
            $images = [...$images, ...\array_map($medal->badge(...), \range(1, 5))];
        }

        foreach ($checker->customMedals() as $medal) {
            $images = [...$images, ...\array_map($medal->image(...), $medal->tiers ?: [''])];
        }

        foreach ($images as $image) {
            yield $image => [$image];
        }
    }

    #[DataProvider('imageProvider')]
    public function testTheImageExists(string $image): void
    {
        [$prefix, $file] = \explode('/', $image, 2);
        self::assertFileExists(self::IMAGE_DIRS[$prefix.'/'].$file);
    }

    private function stat(int $explorer, int $recon, ?int $seer): AgentStat
    {
        return new AgentStat(new User(), new AgentStatInput(
            datetime: '2026-10-19 15:35:56',
            nickname: 'TestAgent',
            faction: 'Enlightened',
            ap: 1,
            explorer: $explorer,
            recon: $recon,
            seer: $seer,
        ));
    }
}
