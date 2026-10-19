<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UploadSource;
use App\Repository\UploadRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity(repositoryClass: UploadRepository::class)]
class Upload
{
    #[Column, Id, GeneratedValue]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<mixed> $data
     */
    public function __construct(
        #[ManyToOne, JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $owner,
        #[Column(length: 255)]
        private string $title,
        #[Column(type: Types::JSON)]
        private array $data,
        #[Column(length: 20, enumType: UploadSource::class)]
        private UploadSource $source,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getSource(): UploadSource
    {
        return $this->source;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
