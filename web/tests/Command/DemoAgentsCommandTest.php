<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Enum\ProfilePart;
use App\Repository\AgentStatRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DemoAgentsCommandTest extends KernelTestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->tester = new CommandTester(new Application(self::bootKernel())->find('app:demo-agents'));
    }

    protected function tearDown(): void
    {
        $this->tester->execute(['--remove' => true]);

        parent::tearDown();
    }

    public function testAddsAndReplacesTheDemoAgents(): void
    {
        self::assertSame(Command::SUCCESS, $this->tester->execute([]));
        self::assertSame(Command::SUCCESS, $this->tester->execute([]));
        self::assertStringContainsString('Added 8 demo agents', $this->tester->getDisplay());
        self::assertStringContainsString('8 replaced', $this->tester->getDisplay());

        $frog = $this->users()->findOneByAgentName('DemoFrog');
        self::assertNotNull($frog);
        self::assertTrue($frog->isVisible(ProfilePart::CUSTOM_MEDALS));
        self::assertStringStartsWith('data:image/jpeg;base64,', (string) $frog->getVisibleAvatar());
        $stats = $this->stats()->findByOwner($frog);
        self::assertCount(6, $stats);
        // Newest first, growing
        self::assertGreaterThan($stats[1]->ap, $stats[0]->ap);
        self::assertNull($this->users()->findOneByAgentName('DemoNew')?->getVisibilityChosenAt());
    }

    public function testRemovesTheDemoAgents(): void
    {
        $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $this->tester->execute(['--remove' => true]));

        self::assertStringContainsString('Removed 8 demo agents.', $this->tester->getDisplay());
        self::assertNull($this->users()->findOneByAgentName('DemoFrog'));
        // The others stay
        self::assertNotNull($this->users()->findOneBy(['identifier' => 'admin']));
    }

    private function users(): UserRepository
    {
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);

        return $users;
    }

    private function stats(): AgentStatRepository
    {
        /** @var AgentStatRepository $stats */
        $stats = self::getContainer()->get(AgentStatRepository::class);

        return $stats;
    }
}
