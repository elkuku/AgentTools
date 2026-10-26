<?php

declare(strict_types=1);

namespace App\Command;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
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
 * Demo agents with made-up stats, custom medals, pictures and every kind of visibility, to try the
 * agent pages in dev. Running it again replaces them; --remove only removes them. Fixtures are
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

    /** Agent name => faction, level, share of VETERAN, imports, visible parts, picture, custom medals. */
    private const array AGENTS = [
        'DemoFrog' => ['Enlightened', 16, 1.2, 6, [ProfilePart::AGENT, ProfilePart::AVATAR, ProfilePart::LATEST_STATS, ProfilePart::STATS_HISTORY, ProfilePart::CUSTOM_MEDALS], true,
            ['anomaly' => ['orion' => 'platinum', 'echo' => '', 'plus_gamma' => 'gold'], 'annual' => ['voyager' => 5, 'sage' => 3], 'event' => ['csans' => 'gold']]],
        'DemoKey' => ['Resistance', 14, 0.7, 5, [ProfilePart::AGENT, ProfilePart::AVATAR, ProfilePart::LATEST_STATS, ProfilePart::STATS_HISTORY, ProfilePart::CUSTOM_MEDALS], true,
            ['anomaly' => ['apollo' => 'silver'], 'annual' => ['catalyst' => 2], 'event' => ['2026_summer_solstice' => 'bronze']]],
        'DemoOwl' => ['Enlightened', 12, 0.35, 4, [ProfilePart::AGENT, ProfilePart::LATEST_STATS], false, []],
        'DemoFox' => ['Resistance', 10, 0.2, 4, [ProfilePart::AGENT, ProfilePart::AVATAR, ProfilePart::STATS_HISTORY], true, []],
        'DemoBee' => ['Enlightened', 8, 0.08, 2, [ProfilePart::AGENT, ProfilePart::CUSTOM_MEDALS], true,
            ['anomaly' => ['ctrl' => ''], 'annual' => ['paragon' => 1]]],
        'DemoCat' => ['Resistance', 5, 0.02, 1, [ProfilePart::AGENT], false, []],
        // Chose to keep everything private, and one who has not chosen yet
        'DemoAnt' => ['Enlightened', 11, 0.3, 3, [], true, []],
        self::NOT_CHOSEN => ['Resistance', 3, 0.005, 1, [], false, []],
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

        foreach (self::AGENTS as $agentName => [$faction, $level, $share, $imports, $parts, $avatar, $customMedals]) {
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
            $this->addStats($user, $agentName, $faction, $level, $share, $imports);
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

        $this->entityManager->createQuery('DELETE FROM '.AgentStat::class.' s WHERE s.owner IN (:users)')
            ->setParameter('users', $users)
            ->execute();
        $this->entityManager->createQuery('DELETE FROM '.User::class.' u WHERE u IN (:users)')
            ->setParameter('users', $users)
            ->execute();

        return \count($users);
    }

    /**
     * [$imports] stats, two weeks apart up to today, growing to [$share] of a veteran's values.
     */
    private function addStats(User $user, string $agentName, string $faction, int $level, float $share, int $imports): void
    {
        // The same numbers on every run
        \mt_srand(\crc32($agentName));
        $spread = \array_map(static fn (): float => \mt_rand(60, 120) / 100, self::VETERAN);

        for ($import = 1; $import <= $imports; ++$import) {
            $progress = $share * (0.7 + 0.3 * $import / $imports);
            $values = [];
            foreach (self::VETERAN as $field => $veteran) {
                $values[$field] = (int) \round($veteran * $progress * $spread[$field]);
            }

            $this->entityManager->persist(new AgentStat($user, new AgentStatInput(...[
                ...$values,
                'datetime' => new \DateTimeImmutable('today 18:00')->modify(\sprintf('-%d weeks', 2 * ($imports - $import)))->format(AgentStatInput::DATETIME_FORMAT),
                'nickname' => $agentName,
                'faction' => $faction,
                'level' => \max(1, $level - \intdiv($imports - $import, 3)),
                'recursions' => $level >= 16 ? 1 : 0,
            ])));
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
