<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261104160457 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The history row a timeline event was marked from';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timeline_event ADD history_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX timeline_event_owner_history_key ON timeline_event (owner_id, history_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX timeline_event_owner_history_key');
        $this->addSql('ALTER TABLE timeline_event DROP history_key');
    }
}
