<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The Google profile picture as a data: URI, and the URL it came from.
 */
final class Version20261026151537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add system_user.avatar and avatar_source';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE system_user ADD avatar TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE system_user ADD avatar_source VARCHAR(1000) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE system_user DROP avatar');
        $this->addSql('ALTER TABLE system_user DROP avatar_source');
    }
}
