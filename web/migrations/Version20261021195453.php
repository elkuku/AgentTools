<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Agent stats and custom medals need ROLE_AGENT now: users who already have some keep access.
 */
final class Version20261021195453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give ROLE_AGENT to users who already uploaded agent stats or custom medals';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE system_user SET role = 'ROLE_AGENT'
            WHERE role = 'ROLE_USER'
              AND (id IN (SELECT owner_id FROM agent_stat) OR custom_medals::text <> '{}')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE system_user SET role = 'ROLE_USER' WHERE role = 'ROLE_AGENT'");
    }
}
