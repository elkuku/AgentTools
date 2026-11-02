<?php

declare(strict_types=1);

namespace App\Tests\Controller\AgentStat;

use App\Entity\User;
use App\Import\CsvImportJob;
use App\Notifier\ChatSender;
use App\Repository\AgentStatRepository;
use App\Repository\UserRepository;
use App\Tests\Import\AgentStatCsvImporterTest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Event\MessageEvent;

/**
 * The CSV import on /agent-stats/import, linked in the user menu, at once or in parts (CsvImportJob).
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
        // Unfinished imports
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDir);
        \array_map(\unlink(...), \glob($projectDir.'/var/imports/test/*.csv') ?: []);

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
        self::assertSame([\sprintf('📥 <http://localhost/admin/agents/%d|TestAgent> imported 1 stats upload', $this->user()->id)], $this->chatMessages());
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

    public function testImportsABigFileInParts(): void
    {
        $rows = [AgentStatCsvImporterTest::HEADER];
        for ($day = 0; $day < CsvImportJob::BATCH + 50; ++$day) {
            $rows[] = AgentStatCsvImporterTest::row(['datetime' => new \DateTimeImmutable('2022-01-01 18:00:00')->modify(\sprintf('+%d days', $day))->format('Y-m-d H:i:s'), 'ap' => 15_000_000 + $day, 'level' => 8, 'faction' => 'Enlightened', 'nickname' => 'TestAgent']);
        }

        $rows[] = AgentStatCsvImporterTest::row(['datetime' => '2030-01-01 18:00:00', 'ap' => 1, 'level' => 8, 'faction' => 'Enlightened', 'nickname' => 'Other']);
        \file_put_contents($this->file, \implode("\n", $rows));

        $this->client->loginUser($this->user());
        $this->startImport($this->file);
        self::assertResponseIsSuccessful();
        $start = $this->json();
        self::assertSame(CsvImportJob::BATCH + 51, $start['rows']);
        self::assertCount(0, $this->stats()->findByOwner($this->user()));

        // A part per request
        $this->client->request(Request::METHOD_POST, (string) $start['next']);
        self::assertSame(['done' => CsvImportJob::BATCH, 'rows' => CsvImportJob::BATCH + 51, 'imported' => CsvImportJob::BATCH, 'finished' => false], $this->json());
        self::assertSame([], $this->chatMessages());

        $this->client->request(Request::METHOD_POST, (string) $start['next']);
        self::assertSame(['done' => CsvImportJob::BATCH + 51, 'rows' => CsvImportJob::BATCH + 51, 'imported' => CsvImportJob::BATCH + 50, 'finished' => true, 'redirect' => '/agent-stats'], $this->json());
        self::assertCount(CsvImportJob::BATCH + 50, $this->stats()->findByOwner($this->user()));
        self::assertSame([\sprintf('📥 <http://localhost/admin/agents/%d|TestAgent> imported 150 stats uploads', $this->user()->id)], $this->chatMessages());

        // Done: the result on the user's stats, the import is gone
        $this->client->request(Request::METHOD_GET, '/agent-stats');
        self::assertSelectorTextContains('.alert-success', 'Imported 150 stats, skipped 0 you already had.');
        self::assertSelectorTextContains('.alert-warning li', '152: These stats are for agent Other');
        $this->client->request(Request::METHOD_POST, (string) $start['next']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testThePartsOnlyForTheUser(): void
    {
        \file_put_contents($this->file, \implode("\n", [
            AgentStatCsvImporterTest::HEADER,
            AgentStatCsvImporterTest::row(['datetime' => '2022-01-06 18:00:00', 'ap' => 15_000_000, 'level' => 8, 'faction' => 'Enlightened', 'nickname' => 'TestAgent']),
            AgentStatCsvImporterTest::row(['datetime' => '2022-01-07 18:00:00', 'ap' => 15_000_001, 'level' => 8, 'faction' => 'Enlightened', 'nickname' => 'TestAgent']),
        ]));

        $this->client->loginUser($this->user());
        $this->startImport($this->file);
        $next = (string) $this->json()['next'];

        // Another user (another session)
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($this->otherUser());
        $this->client->request(Request::METHOD_POST, $next);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertCount(0, $this->stats()->findByOwner($this->user()));
    }

    public function testThePartsAreOnlyForCsvFiles(): void
    {
        $json = \tempnam(\sys_get_temp_dir(), 'medals').'.json';
        \file_put_contents($json, '{"anomaly": {}}');

        $this->client->loginUser($this->user());
        $form = $this->startImport($json);
        \unlink($json);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Without a file: the form's error
        $this->client->request(Request::METHOD_POST, '/agent-stats/import/start', $form->getPhpValues());
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(['Choose a CSV file to import.'], $this->json()['errors']);
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

    /**
     * Sends the import page's form with [$file] to the start of an import in parts, as
     * stats_import_controller.js does.
     */
    private function startImport(string $file): Form
    {
        $this->client->request(Request::METHOD_GET, '/agent-stats/import');
        // The progress bar for it
        self::assertSelectorExists('[data-controller="stats-import"] #stats-import-progress[hidden]');
        $form = $this->client->getCrawler()->selectButton('Import')->form();
        $field = $form['stats_import[file]'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($file);
        $this->client->request(Request::METHOD_POST, '/agent-stats/import/start', $form->getPhpValues(), $form->getPhpFiles());

        return $form;
    }

    private function otherUser(): User
    {
        $users = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->findOneBy(['identifier' => 'admin']);
        self::assertNotNull($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        $json = \json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($json);

        return $json;
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

    /**
     * What was posted to Slack (or Telegram) in the last request.
     *
     * @return list<string>
     */
    private function chatMessages(string $transport = ChatSender::SLACK): array
    {
        return \array_values(\array_map(
            static fn (MessageEvent $event): string => $event->getMessage()->getSubject(),
            \array_filter(self::getNotifierEvents(), static fn (MessageEvent $event): bool => !$event->isQueued() && $transport === $event->getMessage()->getTransport()),
        ));
    }
}
