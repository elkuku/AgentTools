<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the app synced from Bannergress for the signed-in agent: part of Me (GET / PATCH /api/me),
 * and of Agent for the agents who show it. Bannergress only answers signed-in users, so the app
 * signs in there and sends the counts with every published banner, the newest banners of the
 * to-do list and every banner of the done list (stored as DoneBanner, where the user adds the day
 * it was done).
 */
final class Bannergress
{
    /** The newest banners kept of the to-do list. */
    public const int MAX_BANNERS = 20;

    /** The most published banners a sync takes. */
    public const int MAX_PUBLISHED_BANNERS = 2000;

    /** The most banners of the done list a sync takes. */
    public const int MAX_DONE_BANNERS = 20000;

    /** A banner picture as Bannergress has it, relative to PICTURE_BASE. */
    public const string PICTURE_PATTERN = '/^\/bnrs\/pictures\/[A-Za-z0-9]{1,100}$/';

    public const string PICTURE_BASE = 'https://api.bannergress.com';

    public const string BANNER_BASE = 'https://bannergress.com/banner/';

    /**
     * @param list<array{id: string, title: string, missions: int, picture: string|null, startLatitude?: float|int|null, startLongitude?: float|int|null}>                               $todoBanners      newest first
     * @param list<array{id: string, title: string, missions: int, picture: string|null, startLatitude?: float|int|null, startLongitude?: float|int|null, eventStartDate?: string|null}> $doneBanners      newest first, written only
     * @param list<array{id: string, title: string, missions: int, picture: string|null, startLatitude?: float|int|null, startLongitude?: float|int|null}>                               $publishedBanners newest first
     */
    public function __construct(
        /** Banners with a mission the agent created. */
        #[Groups([Me::READ, Me::WRITE, Agent::ITEM])]
        #[Assert\PositiveOrZero]
        public int $published = 0,
        #[Groups([Me::READ, Me::WRITE, Agent::ITEM])]
        #[Assert\PositiveOrZero]
        public int $todo = 0,
        #[Groups([Me::READ, Me::WRITE, Agent::ITEM])]
        #[Assert\PositiveOrZero]
        public int $done = 0,
        #[Groups([Me::READ, Me::WRITE, Agent::ITEM])]
        #[Assert\Count(max: self::MAX_BANNERS)]
        #[Assert\All([new Assert\Collection(fields: [
            'id' => [new Assert\Type('string'), new Assert\NotBlank(), new Assert\Length(max: 200)],
            'title' => [new Assert\Type('string'), new Assert\Length(max: 200)],
            'missions' => [new Assert\Type('int'), new Assert\PositiveOrZero()],
            'picture' => new Assert\Optional([new Assert\Type('string'), new Assert\Regex(self::PICTURE_PATTERN)]),
            'startLatitude' => new Assert\Optional([new Assert\Type(['int', 'float']), new Assert\Range(min: -90, max: 90)]),
            'startLongitude' => new Assert\Optional([new Assert\Type(['int', 'float']), new Assert\Range(min: -180, max: 180)]),
        ])])]
        public array $todoBanners = [],
        /** Every banner of the done list, newest first: the app sends them, they are stored as DoneBanner. */
        #[Groups([Me::WRITE])]
        #[Assert\Count(max: self::MAX_DONE_BANNERS)]
        #[Assert\All([new Assert\Collection(fields: [
            'id' => [new Assert\Type('string'), new Assert\NotBlank(), new Assert\Length(max: 200)],
            'title' => [new Assert\Type('string'), new Assert\Length(max: 200)],
            'missions' => [new Assert\Type('int'), new Assert\PositiveOrZero()],
            'picture' => new Assert\Optional([new Assert\Type('string'), new Assert\Regex(self::PICTURE_PATTERN)]),
            'startLatitude' => new Assert\Optional([new Assert\Type(['int', 'float']), new Assert\Range(min: -90, max: 90)]),
            'startLongitude' => new Assert\Optional([new Assert\Type(['int', 'float']), new Assert\Range(min: -180, max: 180)]),
            // The first day of an event banner: the day it was done unless the user set one
            'eventStartDate' => new Assert\Optional([new Assert\Type('string'), new Assert\Date()]),
        ])])]
        public array $doneBanners = [],
        /** Every banner with a mission the agent created, newest first. */
        #[Groups([Me::READ, Me::WRITE, Agent::ITEM])]
        #[Assert\Count(max: self::MAX_PUBLISHED_BANNERS)]
        #[Assert\All([new Assert\Collection(fields: [
            'id' => [new Assert\Type('string'), new Assert\NotBlank(), new Assert\Length(max: 200)],
            'title' => [new Assert\Type('string'), new Assert\Length(max: 200)],
            'missions' => [new Assert\Type('int'), new Assert\PositiveOrZero()],
            'picture' => new Assert\Optional([new Assert\Type('string'), new Assert\Regex(self::PICTURE_PATTERN)]),
            'startLatitude' => new Assert\Optional([new Assert\Type(['int', 'float']), new Assert\Range(min: -90, max: 90)]),
            'startLongitude' => new Assert\Optional([new Assert\Type(['int', 'float']), new Assert\Range(min: -180, max: 180)]),
        ])])]
        public array $publishedBanners = [],
        /** When the app synced it (ISO 8601); the server sets it. */
        #[Groups([Me::READ, Agent::ITEM])]
        public ?string $syncedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed>|null $stored as stored on the User, null when not synced yet
     */
    public static function fromStored(?array $stored): ?self
    {
        return null === $stored ? null : self::fromArray($stored);
    }

    /**
     * @param array<string, mixed> $stored as stored on the User
     */
    public static function fromArray(array $stored): self
    {
        /* @var array{published?: int, todo?: int, done?: int, todoBanners?: list<array{id: string, title: string, missions: int, picture: string|null, startLatitude?: float|int|null, startLongitude?: float|int|null}>, doneBanners?: list<array{id: string, title: string, missions: int, picture: string|null, startLatitude?: float|int|null, startLongitude?: float|int|null}>, syncedAt?: string} $stored */
        return new self(
            $stored['published'] ?? 0,
            $stored['todo'] ?? 0,
            $stored['done'] ?? 0,
            $stored['todoBanners'] ?? [],
            publishedBanners: $stored['publishedBanners'] ?? [],
            syncedAt: $stored['syncedAt'] ?? null,
        );
    }

    /**
     * @return array<string, mixed> for the User, without the done banners (DoneBanner)
     */
    public function toArray(): array
    {
        $banner = static fn (array $banner): array => [
            'id' => $banner['id'],
            'title' => $banner['title'],
            'missions' => $banner['missions'],
            'picture' => $banner['picture'] ?? null,
            'startLatitude' => $banner['startLatitude'] ?? null,
            'startLongitude' => $banner['startLongitude'] ?? null,
        ];

        return [
            'published' => $this->published,
            'todo' => $this->todo,
            'done' => $this->done,
            'todoBanners' => \array_map($banner, $this->todoBanners),
            'publishedBanners' => \array_map($banner, $this->publishedBanners),
            'syncedAt' => $this->syncedAt,
        ];
    }
}
