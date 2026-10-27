<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\AgentProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Other agents' profiles for the app, as web's /agents and /agents/{agentName}: only agents who
 * show the agent part, and only the parts they show (null for the others).
 */
#[ApiResource(
    description: 'Agents who show their profile to other users (the agent part, see Me::visibleParts), and the parts they show; private parts are null. The list has the agent name, faction, level and avatar; an agent also has latestStats (a stats row with the column names of /api/agent-stats), history (all stats rows, newest first) with events (the custom timeline events: date, endDate (null for one day) and text, newest first), customMedals, bannergress (as Me has it) and doneBanners (as /api/done-banners has them, without the id).',
    operations: [
        new GetCollection(
            uriTemplate: '/agents',
            paginationEnabled: false,
            normalizationContext: ['groups' => [self::READ]],
            security: "is_granted('ROLE_USER')",
            provider: AgentProvider::class,
        ),
        new Get(
            uriTemplate: '/agents/{agentName}',
            requirements: ['agentName' => '[A-Za-z0-9_]{1,50}'],
            normalizationContext: ['groups' => [self::READ, self::ITEM]],
            security: "is_granted('ROLE_USER')",
            provider: AgentProvider::class,
        ),
    ],
)]
final readonly class Agent
{
    public const string READ = 'agent:read';

    public const string ITEM = 'agent:item';

    /**
     * @param array<string, int|string|null>|null                                                                                                                                $latestStats with the latest stats part
     * @param list<array<string, int|string|null>>|null                                                                                                                          $history     with the stats history part, newest first
     * @param list<array{date: string, endDate: string|null, text: string}>|null                                                                                                 $events      the custom timeline events, with the stats history part, newest first
     * @param list<array{bannerId: string, title: string, missions: int, picture: string|null, startLatitude: float|null, startLongitude: float|null, doneAt: string|null}>|null $doneBanners newest on the list first
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups([self::READ])]
        public string $agentName,
        /** From the latest stats; null without stats. */
        #[Groups([self::READ])]
        public ?string $faction = null,
        #[Groups([self::READ])]
        public ?int $level = null,
        /** The Google profile picture (data: URI), with the avatar part. */
        #[Groups([self::READ])]
        public ?string $avatar = null,
        #[Groups([self::ITEM])]
        public ?array $latestStats = null,
        #[Groups([self::ITEM])]
        public ?array $history = null,
        #[Groups([self::ITEM])]
        public ?array $events = null,
        /** With the custom medals part. */
        #[Groups([self::ITEM])]
        public ?CustomMedals $customMedals = null,
        /** With the Bannergress part, once the agent synced it. */
        #[Groups([self::ITEM])]
        public ?Bannergress $bannergress = null,
        /** The done list with the days, with the Bannergress part. */
        #[Groups([self::ITEM])]
        public ?array $doneBanners = null,
    ) {
    }
}
