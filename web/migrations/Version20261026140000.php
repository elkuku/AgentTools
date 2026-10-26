<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Every user uploads agent stats now, there is no Agent role any more.
 */
final class Version20261026140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make the agents users: ROLE_AGENT is removed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE system_user SET role = 'ROLE_USER' WHERE role = 'ROLE_AGENT'");
    }

    public function down(Schema $schema): void
    {
        // Only agents could upload stats: users with stats were agents
        $this->addSql("UPDATE system_user u SET role = 'ROLE_AGENT' WHERE role = 'ROLE_USER' AND EXISTS (SELECT 1 FROM agent_stat s WHERE s.owner_id = u.id)");
    }
}
