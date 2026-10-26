<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Users choose which profile parts other users see; all are private until they choose.
 */
final class Version20261026140002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add system_user.visible_parts and visibility_chosen_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE system_user ADD visible_parts JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE system_user ADD visibility_chosen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE system_user DROP visible_parts');
        $this->addSql('ALTER TABLE system_user DROP visibility_chosen_at');
    }
}
