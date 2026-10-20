<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Dto\AgentStatInput;
use App\Exception\StatsAlreadyAddedException;
use App\Repository\AgentStatRepository;
use App\Security\Voter\AgentStatVoter;
use App\State\AgentStatProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * Ingress all-time agent stats, with the columns of 4e-website's agent_stat table.
 * 4e-website links a row to its Agent; here it belongs to the User who imported it.
 *
 * API: the user's own stats, newest first (OwnerExtension), and POST of an AgentStatInput.
 */
#[Entity(repositoryClass: AgentStatRepository::class)]
#[UniqueConstraint(name: 'agent_stat_owner_datetime', columns: ['owner_id', 'datetime'])]
#[ApiResource(
    description: "Ingress all-time agent stats, with the column names of 4e-website's agent_stat table. Lists only your own, newest first.",
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('".AgentStatVoter::VIEW."', object)"),
        new Post(
            exceptionToStatus: [StatsAlreadyAddedException::class => 409],
            collectDenormalizationErrors: true,
            security: "is_granted('ROLE_USER')",
            input: AgentStatInput::class,
            processor: AgentStatProcessor::class,
        ),
    ],
    order: ['datetime' => 'DESC'],
)]
class AgentStat
{
    #[Column, Id, GeneratedValue]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    /** The same format as AgentStatInput::$datetime. */
    #[Column(type: Types::DATETIME_IMMUTABLE)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => AgentStatInput::DATETIME_FORMAT])]
    public private(set) \DateTimeImmutable $datetime;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $createdAt;

    #[Column(length: 150)]
    public private(set) string $nickname;

    #[Column(length: 50)]
    public private(set) string $faction;

    #[Column]
    public private(set) int $ap;

    #[Column(nullable: true)]
    public private(set) ?int $level;

    #[Column(nullable: true)]
    public private(set) ?int $explorer;

    #[Column(nullable: true)]
    public private(set) ?int $recon;

    #[Column(nullable: true)]
    public private(set) ?int $seer;

    #[Column(nullable: true)]
    public private(set) ?int $trekker;

    #[Column(nullable: true)]
    public private(set) ?int $builder;

    #[Column(nullable: true)]
    public private(set) ?int $connector;

    #[Column(nullable: true)]
    #[SerializedName('mind_controller')]
    public private(set) ?int $mindController;

    #[Column(nullable: true)]
    public private(set) ?int $illuminator;

    #[Column(nullable: true)]
    public private(set) ?int $recharger;

    #[Column(nullable: true)]
    public private(set) ?int $liberator;

    #[Column(nullable: true)]
    public private(set) ?int $pioneer;

    #[Column(nullable: true)]
    public private(set) ?int $engineer;

    #[Column(nullable: true)]
    public private(set) ?int $purifier;

    #[Column(nullable: true)]
    public private(set) ?int $specops;

    #[Column(nullable: true)]
    public private(set) ?int $hacker;

    #[Column(nullable: true)]
    public private(set) ?int $translator;

    #[Column(nullable: true)]
    public private(set) ?int $sojourner;

    #[Column(nullable: true)]
    public private(set) ?int $recruiter;

    #[Column(nullable: true)]
    public private(set) ?int $missionday;

    #[Column(nullable: true)]
    #[SerializedName('nl1331_meetups')]
    public private(set) ?int $nl1331Meetups;

    #[Column(nullable: true)]
    public private(set) ?int $ifs;

    #[Column(nullable: true)]
    #[SerializedName('current_challenge')]
    public private(set) ?int $currentChallenge;

    #[Column(nullable: true)]
    public private(set) ?int $scout;

    #[Column(nullable: true)]
    #[SerializedName('scout_controller')]
    public private(set) ?int $scoutController;

    #[Column(nullable: true)]
    #[SerializedName('longest_link')]
    public private(set) ?int $longestLink;

    #[Column(nullable: true)]
    #[SerializedName('largest_field')]
    public private(set) ?int $largestField;

    #[Column(nullable: true)]
    public private(set) ?int $recursions;

    #[Column(nullable: true)]
    #[SerializedName('drone_flight_distance')]
    public private(set) ?int $droneFlightDistance;

    #[Column(nullable: true)]
    public private(set) ?int $maverick;

    #[Column(nullable: true)]
    #[SerializedName('drone_portals_visited')]
    public private(set) ?int $dronePortalsVisited;

    #[Column(nullable: true)]
    #[SerializedName('drone_forced_recalls')]
    public private(set) ?int $droneForcedRecalls;

    #[Column(nullable: true)]
    #[SerializedName('kinetic_capsules_completed')]
    public private(set) ?int $kineticCapsulesCompleted;

    #[Column(nullable: true)]
    #[SerializedName('months_subscribed')]
    public private(set) ?int $monthsSubscribed;

    #[Column(nullable: true)]
    public private(set) ?int $epoch;

    #[Column(nullable: true)]
    #[SerializedName('portals_discovered')]
    public private(set) ?int $portalsDiscovered;

    #[Column(nullable: true)]
    #[SerializedName('drones_returned')]
    public private(set) ?int $dronesReturned;

    #[Column(nullable: true)]
    #[SerializedName('second_sunday')]
    public private(set) ?int $secondSunday;

    #[Column(nullable: true)]
    public private(set) ?int $reclaimer;

    /**
     * @param AgentStatInput $input validated input
     */
    public function __construct(#[ManyToOne, JoinColumn(nullable: false, onDelete: 'CASCADE'), Ignore]
        public private(set) User $owner, AgentStatInput $input)
    {
        $this->datetime = $input->getDateTime();
        $this->createdAt = new \DateTimeImmutable();
        $this->nickname = \trim($input->nickname);
        $this->faction = \trim($input->faction);
        $this->ap = $input->ap ?? 0;
        $this->level = $input->level;
        $this->explorer = $input->explorer;
        $this->recon = $input->recon;
        $this->seer = $input->seer;
        $this->trekker = $input->trekker;
        $this->builder = $input->builder;
        $this->connector = $input->connector;
        $this->mindController = $input->mindController;
        $this->illuminator = $input->illuminator;
        $this->recharger = $input->recharger;
        $this->liberator = $input->liberator;
        $this->pioneer = $input->pioneer;
        $this->engineer = $input->engineer;
        $this->purifier = $input->purifier;
        $this->specops = $input->specops;
        $this->hacker = $input->hacker;
        $this->translator = $input->translator;
        $this->sojourner = $input->sojourner;
        $this->recruiter = $input->recruiter;
        $this->missionday = $input->missionday;
        $this->nl1331Meetups = $input->nl1331Meetups;
        $this->ifs = $input->ifs;
        $this->currentChallenge = $input->currentChallenge;
        $this->scout = $input->scout;
        $this->scoutController = $input->scoutController;
        $this->longestLink = $input->longestLink;
        $this->largestField = $input->largestField;
        $this->recursions = $input->recursions;
        $this->droneFlightDistance = $input->droneFlightDistance;
        $this->maverick = $input->maverick;
        $this->dronePortalsVisited = $input->dronePortalsVisited;
        $this->droneForcedRecalls = $input->droneForcedRecalls;
        $this->kineticCapsulesCompleted = $input->kineticCapsulesCompleted;
        $this->monthsSubscribed = $input->monthsSubscribed;
        $this->epoch = $input->epoch;
        $this->portalsDiscovered = $input->portalsDiscovered;
        $this->dronesReturned = $input->dronesReturned;
        $this->secondSunday = $input->secondSunday;
        $this->reclaimer = $input->reclaimer;
    }

    /**
     * The stats by agent_stat column name, for display.
     *
     * @return array<string, int|null>
     */
    #[Ignore]
    public function getStats(): array
    {
        return [
            'level' => $this->level,
            'ap' => $this->ap,
            'explorer' => $this->explorer,
            'recon' => $this->recon,
            'seer' => $this->seer,
            'trekker' => $this->trekker,
            'builder' => $this->builder,
            'connector' => $this->connector,
            'mind_controller' => $this->mindController,
            'illuminator' => $this->illuminator,
            'recharger' => $this->recharger,
            'liberator' => $this->liberator,
            'pioneer' => $this->pioneer,
            'engineer' => $this->engineer,
            'purifier' => $this->purifier,
            'specops' => $this->specops,
            'hacker' => $this->hacker,
            'translator' => $this->translator,
            'sojourner' => $this->sojourner,
            'recruiter' => $this->recruiter,
            'missionday' => $this->missionday,
            'nl1331_meetups' => $this->nl1331Meetups,
            'ifs' => $this->ifs,
            'current_challenge' => $this->currentChallenge,
            'scout' => $this->scout,
            'scout_controller' => $this->scoutController,
            'longest_link' => $this->longestLink,
            'largest_field' => $this->largestField,
            'recursions' => $this->recursions,
            'drone_flight_distance' => $this->droneFlightDistance,
            'maverick' => $this->maverick,
            'drone_portals_visited' => $this->dronePortalsVisited,
            'drone_forced_recalls' => $this->droneForcedRecalls,
            'kinetic_capsules_completed' => $this->kineticCapsulesCompleted,
            'months_subscribed' => $this->monthsSubscribed,
            'epoch' => $this->epoch,
            'portals_discovered' => $this->portalsDiscovered,
            'drones_returned' => $this->dronesReturned,
            'second_sunday' => $this->secondSunday,
            'reclaimer' => $this->reclaimer,
        ];
    }
}
