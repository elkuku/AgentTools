<?php

declare(strict_types=1);

namespace App\Command;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\TimelineEvent;
use App\Entity\User;
use App\Enum\ProfilePart;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Demo agents with made-up stats (up to five years of them), timeline events, custom medals,
 * pictures and every kind of visibility, to try the agent pages and the timeline in dev. Running it again replaces them; --remove only removes them. Fixtures are
 * not used: loading them empties the database, and the tests load all of them.
 */
#[AsCommand(name: 'app:demo-agents', description: 'Add (or replace) demo agents with test data, dev only')]
final readonly class DemoAgentsCommand
{
    /** Demo users are recognized by it. */
    private const string IDENTIFIER_SUFFIX = '@demo.example.com';

    /** Values of a long-time agent; each demo agent has a share of them. */
    private const array VETERAN = [
        'ap' => 250_000_000, 'explorer' => 30_000, 'recon' => 5_000, 'trekker' => 3_000, 'builder' => 300_000,
        'connector' => 60_000, 'mindController' => 30_000, 'illuminator' => 5_000_000, 'recharger' => 30_000_000,
        'liberator' => 30_000, 'pioneer' => 15_000, 'engineer' => 40_000, 'purifier' => 200_000, 'specops' => 400,
        'hacker' => 200_000, 'translator' => 40_000, 'sojourner' => 400, 'epoch' => 50, 'missionday' => 15,
        'nl1331Meetups' => 10, 'ifs' => 20, 'secondSunday' => 10, 'scout' => 3_000, 'scoutController' => 8_000,
        'maverick' => 9_000, 'reclaimer' => 5_000, 'dronePortalsVisited' => 400, 'droneForcedRecalls' => 100,
        'droneFlightDistance' => 10, 'kineticCapsulesCompleted' => 1_000, 'monthsSubscribed' => 70,
    ];

    /** Has not chosen the visible parts yet: asked on sign-in. */
    private const string NOT_CHOSEN = 'DemoNew';

    /** Every visible part. */
    private const array ALL_PARTS = [ProfilePart::AGENT, ProfilePart::AVATAR, ProfilePart::LATEST_STATS, ProfilePart::STATS_HISTORY, ProfilePart::CUSTOM_MEDALS];

    /**
     * Agent name => faction, level, share of VETERAN, imports and the days between them, visible
     * parts, picture, custom medals and timeline events (days ago, days long or null for one day, text).
     */
    private const array AGENTS = [
        // Plays for five years, an import every 45 days
        'DemoFrog' => ['Enlightened', 16, 1.2, [40, 45], self::ALL_PARTS, true,
            ['anomaly' => ['orion' => 'platinum', 'echo' => '', 'plus_gamma' => 'gold'], 'annual' => ['voyager' => 5, 'sage' => 3], 'event' => ['csans' => 'gold']],
            [
                [1780, null, 'Installed Ingress'],
                [1500, null, 'First Saturday Quito'],
                [1320, 9, 'Travel to Galápagos'],
                [1100, null, 'Anomaly Darsana, Bogotá'],
                [860, 14, 'Travel to Germany'],
                [700, null, 'Mission Day Cuenca'],
                [520, 6, 'Road trip to Lima'],
                [365, null, 'Anomaly Orion, Medellín'],
                [180, 21, 'Travel to Spain'],
                [60, null, 'Recursion'],
                [20, 4, 'Weekend in Guayaquil'],
            ]],
        // Two years, monthly
        'DemoKey' => ['Resistance', 14, 0.7, [24, 30], self::ALL_PARTS, true,
            ['anomaly' => ['apollo' => 'silver'], 'annual' => ['catalyst' => 2], 'event' => ['2026_summer_solstice' => 'bronze']],
            [[690, null, 'Joined the Resistance'], [400, 10, 'Travel to Mexico'], [150, null, 'Anomaly Apollo'], [45, 3, 'Mission day weekend']]],
        'DemoOwl' => ['Enlightened', 12, 0.35, [10, 21], [ProfilePart::AGENT, ProfilePart::LATEST_STATS], false, [], [[100, null, 'A private note']]],
        'DemoFox' => ['Resistance', 10, 0.2, [8, 14], [ProfilePart::AGENT, ProfilePart::AVATAR, ProfilePart::STATS_HISTORY], true, [],
            [[80, null, 'First Saturday'], [30, 5, 'Holidays at the beach']]],
        'DemoBee' => ['Enlightened', 8, 0.08, [4, 14], [ProfilePart::AGENT, ProfilePart::CUSTOM_MEDALS], true,
            ['anomaly' => ['ctrl' => ''], 'annual' => ['paragon' => 1]], []],
        'DemoCat' => ['Resistance', 5, 0.02, [1, 14], [ProfilePart::AGENT], false, [], []],
        // Chose to keep everything private, and one who has not chosen yet
        'DemoAnt' => ['Enlightened', 11, 0.3, [6, 30], [], true, [], [[50, 7, 'Secret trip']]],
        self::NOT_CHOSEN => ['Resistance', 3, 0.005, [1, 14], [], false, [], []],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        #[Autowire(param: 'kernel.environment')] private string $environment,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'Only remove the demo agents')] bool $remove = false): int
    {
        if ('prod' === $this->environment) {
            $io->error('Demo agents are for dev only.');

            return Command::FAILURE;
        }

        $removed = $this->removeDemoAgents();
        if ($remove) {
            $io->success(\sprintf('Removed %d demo agents.', $removed));

            return Command::SUCCESS;
        }

        foreach (self::AGENTS as $agentName => [$faction, $level, $share, [$imports, $every], $parts, $avatar, $customMedals, $events]) {
            $user = new User()
                ->setIdentifier(\strtolower($agentName).self::IDENTIFIER_SUFFIX)
                ->setAgentName($agentName)
                ->setCustomMedals($customMedals);
            if ($avatar) {
                $user->setAvatar($this->avatar($agentName, $faction), null);
            }

            if (self::NOT_CHOSEN !== $agentName) {
                $user->chooseVisibleParts($parts, new \DateTimeImmutable());
            }

            $this->entityManager->persist($user);
            $this->addStats($user, $agentName, $faction, $level, $share, $imports, $every);
            $this->addEvents($user, $events);
        }

        $this->entityManager->flush();

        $io->success(\sprintf('Added %d demo agents (identifiers *%s); %d replaced.', \count(self::AGENTS), self::IDENTIFIER_SUFFIX, $removed));

        return Command::SUCCESS;
    }

    private function removeDemoAgents(): int
    {
        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u.identifier LIKE :suffix')
            ->setParameter('suffix', '%'.self::IDENTIFIER_SUFFIX)
            ->getQuery()
            ->getResult();

        if ([] === $users) {
            return 0;
        }

        foreach ([AgentStat::class, TimelineEvent::class] as $class) {
            $this->entityManager->createQuery('DELETE FROM '.$class.' s WHERE s.owner IN (:users)')
                ->setParameter('users', $users)
                ->execute();
        }

        $this->entityManager->createQuery('DELETE FROM '.User::class.' u WHERE u IN (:users)')
            ->setParameter('users', $users)
            ->execute();

        return \count($users);
    }

    /**
     * [$imports] stats, [$every] days apart up to today. A long history starts small and grows to
     * [$share] of a veteran's values, irregularly: some periods are breaks, some bursts (an
     * anomaly, a holiday), and each stat varies around that. The level rises with it.
     */
    private function addStats(User $user, string $agentName, string $faction, int $level, float $share, int $imports, int $every): void
    {
        // The same numbers on every run
        \mt_srand(\crc32($agentName));
        $spread = \array_map(static fn (): float => \mt_rand(60, 120) / 100, self::VETERAN);
        // A few months of history start at 70 %, years from almost nothing
        $start = \max(0.02, 0.7 - $imports * $every / 1000);

        // How much each stat grew in each period, as a share of its total growth
        $pace = $this->pace($imports);
        $growth = \array_map(
            static fn (): array => self::shares(\array_map(static fn (float $period): float => $period * \mt_rand(60, 140) / 100, $pace)),
            self::VETERAN,
        );

        $done = \array_fill_keys(\array_keys(self::VETERAN), 0.0);
        $played = 0.0;
        $paceShares = self::shares($pace);
        for ($import = 1; $import <= $imports; ++$import) {
            $played += $paceShares[$import - 1];
            $values = [];
            foreach (self::VETERAN as $field => $veteran) {
                $done[$field] += $growth[$field][$import - 1];
                $values[$field] = (int) \round($veteran * $share * ($start + (1 - $start) * $done[$field]) * $spread[$field]);
            }

            $this->entityManager->persist(new AgentStat($user, new AgentStatInput(...[
                ...$values,
                'datetime' => new \DateTimeImmutable('today 18:00')->modify(\sprintf('-%d days', $every * ($imports - $import)))->format(AgentStatInput::DATETIME_FORMAT),
                'nickname' => $agentName,
                'faction' => $faction,
                // Levels come faster than AP: the top level after a third of the play
                'level' => \max(1, (int) \round($level * \min(1.0, $start + (1 - $start) * 3 * $played))),
                // A recursion in the last two months
                'recursions' => $level >= 16 && $every * ($imports - $import) <= 60 ? 1 : 0,
            ])));
        }
    }

    /**
     * How much an agent plays in each of [$periods] periods: mostly 0.3 to 2, sometimes a break
     * (almost nothing), sometimes a burst (three times as much). The first period is the start.
     *
     * @return list<float>
     */
    private function pace(int $periods): array
    {
        $pace = [0.0];
        for ($period = 2; $period <= $periods; ++$period) {
            $pace[] = match (true) {
                \mt_rand(1, 100) <= 15 => 0.05,
                \mt_rand(1, 100) <= 12 => 3 * \mt_rand(80, 200) / 100,
                default => \mt_rand(30, 200) / 100,
            };
        }

        return $pace;
    }

    /**
     * [$weights] as shares of their sum (all 0 for a single period).
     *
     * @param list<float> $weights
     *
     * @return list<float>
     */
    private static function shares(array $weights): array
    {
        $sum = \array_sum($weights);

        return \array_map(static fn (float $weight): float => $sum > 0 ? $weight / $sum : 0.0, $weights);
    }

    /**
     * @param list<array{int, int|null, string}> $events days ago, days long (null: one day), text
     */
    private function addEvents(User $user, array $events): void
    {
        foreach ($events as [$daysAgo, $days, $text]) {
            $event = new TimelineEvent();
            $event->owner = $user;
            $event->date = new \DateTimeImmutable('today')->modify(\sprintf('-%d days', $daysAgo));
            $event->endDate = null === $days ? null : $event->date->modify(\sprintf('+%d days', $days - 1));
            $event->text = $text;
            $this->entityManager->persist($event);
        }
    }

    /** A 100 px picture with the initials on the faction color, as the stored Google pictures. */
    private function avatar(string $agentName, string $faction): string
    {
        $small = \imagecreatetruecolor(20, 20);
        [$red, $green, $blue] = 'Enlightened' === $faction ? [26, 182, 13] : [0, 100, 220];
        \imagefill($small, 0, 0, (int) \imagecolorallocate($small, $red, $green, $blue));
        \imagestring($small, 3, 3, 3, \substr($agentName, 4, 2), (int) \imagecolorallocate($small, 255, 255, 255));

        $image = \imagescale($small, 100, 100, \IMG_NEAREST_NEIGHBOUR);
        \ob_start();
        \imagejpeg($image ?: $small, null, 90);

        return 'data:image/jpeg;base64,'.\base64_encode((string) \ob_get_clean());
    }
}
