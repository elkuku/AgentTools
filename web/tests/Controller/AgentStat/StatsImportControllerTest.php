<?php

declare(strict_types=1);

namespace App\Tests\Controller\AgentStat;

use App\Entity\User;
use App\Repository\AgentStatRepository;
use App\Repository\UserRepository;
use App\Tests\Import\AgentStatCsvImporterTest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The CSV import on /agent-stats/import, linked in the user menu.
 */
class StatsImportControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private string $file;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->user()->setAgentName('TestAgent');
        $this->entityManager()->flush();
        $this->file = \tempnam(\sys_get_temp_dir(), 'stats').'.csv';
    }

    protected function tearDown(): void
    {
        $this->entityManager()->createQuery('DELETE FROM App\Entity\AgentStat')->execute();
        $this->entityManager()->createQuery('UPDATE App\Entity\User u SET u.agentName = NULL')->execute();
        @\unlink($this->file);

        parent::tearDown();
    }

    public function testImportsAFileAndReportsTheRest(): void
    {
        \file_put_contents($this->file, \implode("\n", [
            AgentStatCsvImporterTest::HEADER,
            AgentStatCsvImporterTest::row(['datetime' => '2022-01-06 18:00:00', 'ap' => 15_000_000, 'level' => 8, 'faction' => 'Enlightened', 'nickname' => 'TestAgent']),
            AgentStatCsvImporterTest::row(['datetime' => '2022-02-06 18:00:00', 'ap' => 16_000_000, 'level' => 8, 'faction' => 'Enlightened', 'nickname' => '<b>Other</b>']),
        ]));

        $this->client->loginUser($this->user());
        $this->client->request(Request::METHOD_GET, '/agent-stats');
        // From the user menu
        $this->client->clickLink('Import stats');
        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Import stats from a file');
        $this->client->submitForm('Import', ['stats_import[file]' => $this->file]);

        // The result with the stats
        self::assertResponseRedirects('/agent-stats');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Imported 1 stats, skipped 0 you already had.');
        self::assertSelectorTextContains('.alert-warning', 'One row was not imported:');
        // The file's text is shown as text
        self::assertSelectorTextContains('.alert-warning li', '3: These stats are for agent <b>Other</b>');
        self::assertCount(0, $crawler->filter('.alert-warning li b'));
        self::assertCount(1, $this->stats()->findByOwner($this->user()));
    }

    public function testImportsCustomMedalsFromJson(): void
    {
        $json = \tempnam(\sys_get_temp_dir(), 'medals').'.json';
        \file_put_contents($json, '{"anomaly": {"orion": "platinum"}, "annual": {"voyager": 5}, "event": {}}');

        $this->client->loginUser($this->user());
        $this->client->request(Request::METHOD_GET, '/agent-stats/import');
        $this->client->submitForm('Import', ['stats_import[file]' => $json]);
        \unlink($json);

        self::assertResponseRedirects('/agent-stats');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Custom medals: 2 added, 0 at a higher tier, 0 you already had.');
        self::assertSelectorExists('#custom-medals img[alt="Orion (Platinum)"]');

        $this->user()->setCustomMedals([]);
        $this->entityManager()->flush();
    }

    public function testOnlyCsvFiles(): void
    {
        $image = \tempnam(\sys_get_temp_dir(), 'stats').'.png';
        \file_put_contents($image, 'not stats');

        $this->client->loginUser($this->user());
        $this->client->request(Request::METHOD_GET, '/agent-stats/import');
        $this->client->submitForm('Import', ['stats_import[file]' => $image]);
        \unlink($image);

        // The form again, with the error
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorExists('#stats_import_file.is-invalid');
        self::assertCount(0, $this->stats()->findByOwner($this->user()));
    }

    public function testAFileIsNeeded(): void
    {
        $this->client->loginUser($this->user());
        $this->client->request(Request::METHOD_GET, '/agent-stats/import');
        $this->client->submitForm('Import');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.invalid-feedback', 'Choose a CSV file to import.');
    }

    private function user(): User
    {
        $users = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->findOneBy(['identifier' => 'agent']);
        self::assertNotNull($user);

        return $user;
    }

    private function stats(): AgentStatRepository
    {
        $stats = static::getContainer()->get(AgentStatRepository::class);
        self::assertInstanceOf(AgentStatRepository::class, $stats);

        return $stats;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
