<?php

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

    public function testOpenEntryThrowsOnDoubleOpen(): void
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

        self::assertResponseStatusCodeSame(500);
    }

    public function testCloseEntryThrowsOnMissingDateTime(): void
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

        self::assertResponseStatusCodeSame(500);
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
        self::assertSelectorCount(2, '.accordion-item');
        self::assertSelectorTextContains('.accordion-button', '2024-01-02 12:00');
    }
}
