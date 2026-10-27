<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027181703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add system_user.bannergress: what the app synced from Bannergress';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE system_user ADD bannergress JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE system_user DROP bannergress');
    }
}
