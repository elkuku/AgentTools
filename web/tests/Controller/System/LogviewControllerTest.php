<?php

declare(strict_types=1);

namespace App\Tests\Controller\System;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class LogviewControllerTest extends WebTestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = \dirname(__DIR__, 3).'/var/log/deploy.log';
    }

    protected function tearDown(): void
    {
        if (\file_exists($this->logFile)) {
            \unlink($this->logFile);
        }

        parent::tearDown();
    }

    public function testNonAdminIsAccessDenied(): void
    {
        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['identifier' => 'user']);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request(Request::METHOD_GET, '/system/logview');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessWhenLogFileMissing(): void
    {
        if (\file_exists($this->logFile)) {
            \unlink($this->logFile);
        }

        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['identifier' => 'admin']);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request(Request::METHOD_GET, '/system/logview');

        self::assertResponseIsSuccessful();
    }

    public function testRunWithoutEndLineIsShownAsUnfinished(): void
    {
        \file_put_contents($this->logFile, \implode("\n", [
            '>>>==============',
            '2024-01-01 10:00',
            '>>>==============',
        ]));

        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['identifier' => 'admin']);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request(Request::METHOD_GET, '/system/logview');

        self::assertResponseIsSuccessful();
        // Only the newest run is shown; it has no date line and no end line.
        self::assertSelectorCount(1, '.kuku-deploy-run');
        self::assertSelectorTextContains('.kuku-deploy-run .card-header', 'did not finish');
        // The calendar marks the dated run, in red as it didn't finish either.
        $client->request(Request::METHOD_GET, '/system/logview?month=2024-01');
        self::assertSelectorTextContains('.kuku-deploy-calendar .card-header', 'January 2024');
        self::assertSelectorExists('.kuku-deploy-calendar tbody a.btn-danger');
    }

    public function testRunWithoutDateIsShown(): void
    {
        \file_put_contents($this->logFile, \implode("\n", [
            '>>>==============',
            '<<<===========',
        ]));

        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['identifier' => 'admin']);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request(Request::METHOD_GET, '/system/logview');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.kuku-deploy-run .card-header', '(no date)');
        // A run without a date can't go in the calendar; it is listed below it.
        self::assertSelectorTextContains('.kuku-deploy-undated', '(no date)');
    }

    public function testAdminSeesLogEntries(): void
    {
        \file_put_contents($this->logFile, \implode("\n", [
            '>>>==============',
            '2024-01-01 10:00',
            'Deploy started',
            'Deploy done',
            '<<<===========',
            '>>>==============',
            '2024-01-02 12:00',
            'Second deploy',
            '<<<===========',
        ]));

        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['identifier' => 'admin']);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request(Request::METHOD_GET, '/system/logview');

        self::assertResponseIsSuccessful();
        // The newest run is shown, and both days are marked in the calendar.
        self::assertSelectorTextContains('.kuku-deploy-run .card-header', '02 Jan 2024 · 12:00');
        self::assertSelectorTextContains('.kuku-deploy-run', 'Second deploy');
        self::assertSelectorCount(2, '.kuku-deploy-calendar tbody a');

        // The other run, picked in the calendar.
        $client->request(Request::METHOD_GET, '/system/logview?run=0');
        self::assertSelectorTextContains('.kuku-deploy-run', 'Deploy done');
    }
}
