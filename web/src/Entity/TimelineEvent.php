<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\TimelineEventRepository;
use App\Security\Voter\TimelineEventVoter;
use App\State\TimelineEventProcessor;
use App\Validator\NoOverlappingTravel;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A custom event on the user's timeline (e.g. an anomaly or a mission day), with the stats
 * uploads; with an end date it lasts from [date] to [endDate] (e.g. a travel; travels cannot overlap). Others see the events with the stats history (ProfilePart::STATS_HISTORY).
 *
 * An event added by marking a history row as attended has its historyKey; deleting it removes the mark.
 *
 * API: the user's own events, newest first (OwnerExtension); the owner adds, changes and deletes them.
 */
#[Entity(repositoryClass: TimelineEventRepository::class)]
#[ApiResource(
    description: 'Custom events on your timeline: a date, an optional end date (an event over several days, e.g. a travel; travels cannot overlap: 422) and a text. Lists only your own, newest first; other agents see them with your stats history (GET /api/agents/{agentName} events).',
    operations: [
        new GetCollection(paginationEnabled: false, security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('".TimelineEventVoter::EDIT."', object)"),
        new Post(denormalizationContext: ['groups' => [self::WRITE, self::CREATE]], security: "is_granted('ROLE_USER')", processor: TimelineEventProcessor::class),
        new Patch(security: "is_granted('".TimelineEventVoter::EDIT."', object)"),
        new Delete(security: "is_granted('".TimelineEventVoter::EDIT."', object)"),
    ],
    normalizationContext: ['groups' => [self::READ]],
    denormalizationContext: ['groups' => [self::WRITE]],
    order: ['date' => 'DESC', 'id' => 'DESC'],
)]
#[NoOverlappingTravel]
#[UniqueConstraint(name: 'timeline_event_owner_history_key', columns: ['owner_id', 'history_key'])]
class TimelineEvent
{
    public const string READ = 'timeline_event:read';

    public const string WRITE = 'timeline_event:write';

    /** Only set when adding an event: the attended history row stays linked. */
    public const string CREATE = 'timeline_event:create';

    public const string DATE_FORMAT = 'Y-m-d';

    #[Column, Id, GeneratedValue]
    #[Groups([self::READ])]
    public ?int $id = null;

    /** Set on creation: by the form's controller, or TimelineEventProcessor for the API. */
    #[ManyToOne, JoinColumn(nullable: false, onDelete: 'CASCADE'), Ignore]
    public ?User $owner = null;

    #[Column(type: Types::DATE_IMMUTABLE)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => self::DATE_FORMAT])]
    #[Groups([self::READ, self::WRITE])]
    #[Assert\NotNull]
    public ?\DateTimeImmutable $date = null;

    /** The last day of an event over several days; null for a single day. */
    #[Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => self::DATE_FORMAT])]
    #[Groups([self::READ, self::WRITE])]
    #[Assert\GreaterThanOrEqual(propertyPath: 'date', message: 'The end date must not be before the date.')]
    public ?\DateTimeImmutable $endDate = null;

    #[Column(length: 500)]
    #[Groups([self::READ, self::WRITE])]
    #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 500)]
    public string $text = '';

    /** The history row attended (HistoryAttendance::$key), null for an event the user wrote. */
    #[Column(length: 255, nullable: true)]
    #[Groups([self::READ, self::CREATE])]
    #[Assert\Length(max: 255)]
    public ?string $historyKey = null;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    #[Ignore]
    public private(set) \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
