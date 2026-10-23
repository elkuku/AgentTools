<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Uploaded stats must match the user's agent name now: users with stats get the agent name of
 * their latest stats, unless another user uploaded stats with that name too.
 */
final class Version20261023162722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add system_user.agent_name, set from the latest agent stats';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE system_user ADD agent_name VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9C5F65BFF3B8C4E5 ON system_user (agent_name)');
        $this->addSql(<<<'SQL'
            UPDATE system_user u SET agent_name = latest.nickname
            FROM (
                SELECT DISTINCT ON (owner_id) owner_id, nickname
                FROM agent_stat
                ORDER BY owner_id, datetime DESC
            ) latest
            WHERE u.id = latest.owner_id
              AND latest.nickname ~ '^[A-Za-z0-9_]{1,50}$'
              AND (SELECT COUNT(DISTINCT s.owner_id) FROM agent_stat s WHERE LOWER(s.nickname) = LOWER(latest.nickname)) = 1
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_9C5F65BFF3B8C4E5');
        $this->addSql('ALTER TABLE system_user DROP agent_name');
    }
}
