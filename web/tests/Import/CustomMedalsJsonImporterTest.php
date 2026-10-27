<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Entity\User;
use App\Import\CustomMedalsJsonImporter;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Custom medals from a JSON file as 4e-website exports them (Migrate → Export custom medals).
 */
class CustomMedalsJsonImporterTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        $this->user()->setCustomMedals([]);
        $this->entityManager()->flush();

        parent::tearDown();
    }

    public function testMergesKeepingTheHigherTier(): void
    {
        $this->user()->setCustomMedals(['anomaly' => ['orion' => 'gold', 'echo' => ''], 'annual' => ['voyager' => 5]]);
        $this->entityManager()->flush();

        $result = $this->importer()->import($this->user(), '{
            "anomaly": {"orion": "platinum", "echo": "", "apollo": "silver"},
            "annual": {"voyager": 2, "sage": 3},
            "event": {}
        }');

        self::assertSame(2, $result->added);
        self::assertSame(1, $result->upgraded);
        self::assertSame(2, $result->kept);
        self::assertSame([], $result->errors);
        self::assertSame(
            ['anomaly' => ['orion' => 'platinum', 'echo' => '', 'apollo' => 'silver'], 'annual' => ['voyager' => 5, 'sage' => 3]],
            $this->reloadedUser()->getCustomMedals(),
        );
    }

    public function testReportsWhatItDoesNotKnow(): void
    {
        $result = $this->importer()->import($this->user(), '{"anomaly": {"requiem": "", "orion": "diamond"}, "annual": {"voyager": 6}, "secret": {"x": "y"}}');

        self::assertSame(0, $result->added);
        self::assertSame(['anomaly: requiem ', 'anomaly: orion diamond', 'annual: voyager 6', 'secret: x y'], $result->errors);
        self::assertSame([], $this->reloadedUser()->getCustomMedals());
    }

    public function testNotJson(): void
    {
        $result = $this->importer()->import($this->user(), 'datetime,ap');

        self::assertStringStartsWith('Not JSON: ', $result->errors[0]);
        self::assertSame(
            ['Not custom medals: an object of groups is expected.'],
            $this->importer()->import($this->user(), '"medals"')->errors,
        );
    }

    private function importer(): CustomMedalsJsonImporter
    {
        $importer = self::getContainer()->get(CustomMedalsJsonImporter::class);
        self::assertInstanceOf(CustomMedalsJsonImporter::class, $importer);

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

    /** As stored, not the entity in memory. */
    private function reloadedUser(): User
    {
        $this->entityManager()->clear();

        return $this->user();
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
