<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Entity\User;
use App\Import\AgentStatCsvImporter;
use App\Repository\AgentStatRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Agent stats from a CSV file as 4e-website exports it (Migrate → Export CSV).
 */
class AgentStatCsvImporterTest extends KernelTestCase
{
    /** 4e-website's agent_stat columns without id and agent_id, in its table's order. */
    public const string HEADER = 'datetime,ap,explorer,recon,seer,trekker,builder,connector,mind_controller,illuminator,recharger,liberator,pioneer,engineer,purifier,specops,hacker,translator,sojourner,recruiter,missionday,nl1331_meetups,ifs,current_challenge,level,scout,longest_link,largest_field,recursions,faction,nickname,drone_flight_distance,maverick,drone_portals_visited,scout_controller,drone_forced_recalls,kinetic_capsules_completed,months_subscribed,epoch,portals_discovered,drones_returned,second_sunday,reclaimer';

    protected function setUp(): void
    {
        self::bootKernel();
        $this->user()->setAgentName('TestAgent');
        $this->entityManager()->flush();
    }

    protected function tearDown(): void
    {
        $this->entityManager()->createQuery('DELETE FROM App\Entity\AgentStat')->execute();
        $this->entityManager()->createQuery('UPDATE App\Entity\User u SET u.agentName = NULL')->execute();

        parent::tearDown();
    }

    /**
     * A row with every column: [$values] by column name, the others empty.
     *
     * @param array<string, int|string> $values
     */
    public static function row(array $values): string
    {
        return \implode(',', \array_map(static fn (string $column): string => (string) ($values[$column] ?? ''), \explode(',', self::HEADER)));
    }

    public function testImportsEveryRowOnce(): void
    {
        $csv = self::csv(
            self::HEADER,
            self::row(['datetime' => '2022-01-06 18:00:00', 'ap' => 15_000_000, 'explorer' => 900, 'mind_controller' => 120, 'level' => 8, 'faction' => 'Enlightened', 'nickname' => 'TestAgent', 'current_challenge' => 7, 'longest_link' => 12]),
            '',
            self::row(['datetime' => '2023-01-06 18:00:00', 'ap' => 80_000_000, 'explorer' => 5000, 'level' => 14, 'faction' => 'Enlightened', 'nickname' => 'testagent']),
        );

        $result = $this->importer()->import($this->user(), $csv);

        self::assertSame(2, $result->imported);
        self::assertSame(0, $result->skipped);
        self::assertSame([], $result->errors);

        $stats = $this->stats()->findByOwner($this->user());
        self::assertCount(2, $stats);
        self::assertSame(80_000_000, $stats[0]->ap);
        self::assertSame(120, $stats[1]->getStats()['mind_controller']);
        self::assertSame(12, $stats[1]->getStats()['longest_link']);
        // Not imported again
        \rewind($csv);
        $again = $this->importer()->import($this->user(), $csv);
        self::assertSame(0, $again->imported);
        self::assertSame(2, $again->skipped);
    }

    public function testReportsTheRowsItCannotImport(): void
    {
        $csv = self::csv(
            self::HEADER,
            self::row(['datetime' => '2022-01-06 18:00:00', 'ap' => 1000, 'faction' => 'Enlightened', 'nickname' => 'SomeoneElse']),
            self::row(['datetime' => 'yesterday', 'ap' => 1000, 'faction' => 'Enlightened', 'nickname' => 'TestAgent']),
            self::row(['datetime' => '2022-02-06 18:00:00', 'ap' => 'lots', 'faction' => 'Enlightened', 'nickname' => 'TestAgent']),
            '2022-03-06 18:00:00,1000',
            self::row(['datetime' => '2022-04-06 18:00:00', 'ap' => 1000, 'faction' => 'Enlightened', 'nickname' => 'TestAgent']),
        );

        $result = $this->importer()->import($this->user(), $csv);

        self::assertSame(1, $result->imported);
        self::assertCount(4, $result->errors);
        self::assertSame('2: These stats are for agent SomeoneElse, your agent name is TestAgent.', $result->errors[0]);
        self::assertStringStartsWith('3: datetime: ', $result->errors[1]);
        self::assertStringStartsWith('4: ap: ', $result->errors[2]);
        self::assertSame('5: 2 values for 43 columns', $result->errors[3]);
    }

    public function testOldRowsWithoutNicknameAndFaction(): void
    {
        // As 4e-website's before May 2020
        $csv = self::csv(
            self::HEADER,
            self::row(['datetime' => '2019-01-06 18:00:00', 'ap' => 1000]),
            self::row(['datetime' => '2021-01-06 18:00:00', 'ap' => 9000, 'faction' => 'Resistance', 'nickname' => 'TestAgent']),
        );

        $result = $this->importer()->import($this->user(), $csv);

        self::assertSame(2, $result->imported, \implode("\n", $result->errors));
        $oldest = $this->stats()->findByOwner($this->user())[1];
        self::assertSame('TestAgent', $oldest->nickname);
        self::assertSame('Resistance', $oldest->faction);
    }

    public function testAFileWithoutHeader(): void
    {
        $result = $this->importer()->import($this->user(), self::csv(''));

        self::assertSame(0, $result->imported);
        self::assertSame(['1: no header row'], $result->errors);
    }

    /**
     * @return resource
     */
    public static function csv(string ...$lines)
    {
        $csv = \fopen('php://memory', 'r+');
        self::assertNotFalse($csv);
        \fwrite($csv, \implode("\n", $lines)."\n");
        \rewind($csv);

        return $csv;
    }

    private function importer(): AgentStatCsvImporter
    {
        $importer = self::getContainer()->get(AgentStatCsvImporter::class);
        self::assertInstanceOf(AgentStatCsvImporter::class, $importer);

        return $importer;
    }

    private function user(): User
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->findOneBy(['identifier' => 'agent']);
        self::assertNotNull($user);

        return $user;
    }

    private function stats(): AgentStatRepository
    {
        $stats = self::getContainer()->get(AgentStatRepository::class);
        self::assertInstanceOf(AgentStatRepository::class, $stats);

        return $stats;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
