<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\TimelineEvent;
use App\Enum\ProfilePart;
use App\Repository\AgentStatRepository;
use App\Repository\DoneBannerRepository;
use App\Repository\TimelineEventRepository;
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
        self::assertTrue($frog->isVisible(ProfilePart::BANNERGRESS));
        self::assertSame(412, $frog->getBannergress()['done'] ?? null);
        $doneBanners = static::getContainer()->get(DoneBannerRepository::class);
        self::assertInstanceOf(DoneBannerRepository::class, $doneBanners);
        self::assertCount(7, $doneBanners->findByOwner($frog));
        self::assertCount(5, $doneBanners->findDatedByOwner($frog));
        self::assertStringStartsWith('data:image/jpeg;base64,', (string) $frog->getVisibleAvatar());
        $stats = $this->stats()->findByOwner($frog);
        self::assertCount(40, $stats);
        // Newest first, growing; a veteran of several years who started at level 1
        self::assertGreaterThan($stats[1]->ap, $stats[0]->ap);
        $first = $stats[\count($stats) - 1];
        self::assertGreaterThanOrEqual(4, $first->datetime->diff($stats[0]->datetime)->y);
        self::assertSame(1, $first->level);
        self::assertSame(16, $stats[0]->level);
        // Timeline events, some over several days
        $events = $this->events()->findByOwner($frog);
        self::assertCount(16, $events);
        // Weekends, Saturday and Sunday, and travels over several months
        $weekends = \array_filter($events, static fn (TimelineEvent $event): bool => \str_starts_with($event->text, 'Weekend in'));
        self::assertCount(4, $weekends);
        foreach ($weekends as $weekend) {
            self::assertSame('Sat', $weekend->date?->format('D'));
            self::assertSame('Sun', $weekend->endDate?->format('D'));
        }

        $long = \array_filter($events, static function (TimelineEvent $event): bool {
            $end = $event->endDate;

            return $end instanceof \DateTimeImmutable && $event->date instanceof \DateTimeImmutable && $event->date->diff($end)->days >= 44;
        });
        self::assertCount(2, $long);
        self::assertNotEmpty(\array_filter($events, static fn ($event): bool => null !== $event->endDate));
        // One over the new year, in the past
        $newYear = \array_values(\array_filter($events, static function (TimelineEvent $event): bool {
            $end = $event->endDate;

            return $end instanceof \DateTimeImmutable && $event->date?->format('Y') !== $end->format('Y');
        }));
        self::assertCount(1, $newYear);
        $end = $newYear[0]->endDate;
        self::assertInstanceOf(\DateTimeImmutable::class, $end);
        self::assertSame('12-30', $newYear[0]->date?->format('m-d'));
        self::assertSame('01-03', $end->format('m-d'));
        self::assertLessThan(new \DateTimeImmutable('today'), $end);
        self::assertNull($this->users()->findOneByAgentName('DemoNew')?->getVisibilityChosenAt());
    }

    public function testRemovesTheDemoAgents(): void
    {
        $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $this->tester->execute(['--remove' => true]));

        self::assertStringContainsString('Removed 8 demo agents.', $this->tester->getDisplay());
        self::assertNull($this->users()->findOneByAgentName('DemoFrog'));
        self::assertSame(0, $this->events()->count([]));
        // The others stay
        self::assertNotNull($this->users()->findOneBy(['identifier' => 'admin']));
    }

    private function users(): UserRepository
    {
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);

        return $users;
    }

    private function events(): TimelineEventRepository
    {
        /** @var TimelineEventRepository $events */
        $events = self::getContainer()->get(TimelineEventRepository::class);

        return $events;
    }

    private function stats(): AgentStatRepository
    {
        /** @var AgentStatRepository $stats */
        $stats = self::getContainer()->get(AgentStatRepository::class);

        return $stats;
    }
}
