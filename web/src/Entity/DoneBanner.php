<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\ApiResource\Bannergress;
use App\Repository\DoneBannerRepository;
use App\Security\Voter\DoneBannerVoter;
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

/**
 * A banner on the user's Bannergress done list, as the app synced it (Bannergress sync, see
 * BannergressSync), with the day the user did it: the user sets [doneAt], a sync keeps it (an
 * event banner without a day gets the event's first day).
 * Banners with a day are on the timeline. Others see them with the Bannergress part.
 *
 * API: the user's own, newest on the list first (OwnerExtension); the owner sets the day.
 */
#[Entity(repositoryClass: DoneBannerRepository::class)]
#[UniqueConstraint(name: 'done_banner_owner_banner', fields: ['owner', 'bannerId'])]
#[ApiResource(
    description: 'The banners on your Bannergress done list, as the app synced them (PATCH /api/me bannergress doneBanners), newest on the list first: bannerId (the Bannergress id), title, missions, picture (relative to https://api.bannergress.com), startLatitude / startLongitude (where it starts) and doneAt, the day you did it (Y-m-d, null until you set it; banners with a day are on your timeline). PATCH sets doneAt; a sync keeps it, and gives an event banner without a day the first day of the event (doneBanners eventStartDate).',
    operations: [
        new GetCollection(paginationEnabled: false, security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('".DoneBannerVoter::EDIT."', object)"),
        new Patch(security: "is_granted('".DoneBannerVoter::EDIT."', object)"),
    ],
    normalizationContext: ['groups' => [self::READ]],
    denormalizationContext: ['groups' => [self::WRITE]],
    order: ['position' => 'ASC'],
)]
class DoneBanner
{
    public const string READ = 'done_banner:read';

    public const string WRITE = 'done_banner:write';

    #[Column, Id, GeneratedValue]
    #[Groups([self::READ])]
    public ?int $id = null;

    #[ManyToOne, JoinColumn(nullable: false, onDelete: 'CASCADE'), Ignore]
    public ?User $owner = null;

    /** The Bannergress id, e.g. cologne-cathedral-57d4. */
    #[Column(length: 200)]
    #[Groups([self::READ])]
    public string $bannerId = '';

    #[Column(length: 200)]
    #[Groups([self::READ])]
    public string $title = '';

    #[Column]
    #[Groups([self::READ])]
    public int $missions = 0;

    /** Relative to Bannergress::PICTURE_BASE. */
    #[Column(length: 150, nullable: true)]
    #[Groups([self::READ])]
    public ?string $picture = null;

    /** Where the banner starts. */
    #[Column(nullable: true)]
    #[Groups([self::READ])]
    public ?float $startLatitude = null;

    #[Column(nullable: true)]
    #[Groups([self::READ])]
    public ?float $startLongitude = null;

    /** On the done list: 0 is the newest. */
    #[Column]
    #[Ignore]
    public int $position = 0;

    /** The day the user did it; null until set. */
    #[Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => TimelineEvent::DATE_FORMAT])]
    #[Groups([self::READ, self::WRITE])]
    public ?\DateTimeImmutable $doneAt = null;

    #[Ignore]
    public function getUrl(): string
    {
        return Bannergress::BANNER_BASE.\rawurlencode($this->bannerId);
    }

    #[Ignore]
    public function getPictureUrl(): ?string
    {
        return null === $this->picture ? null : Bannergress::PICTURE_BASE.$this->picture;
    }
}
