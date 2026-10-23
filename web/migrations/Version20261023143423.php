<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023143423 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the GitHub and GitLab IDs of system_user: only Google sign-in is left';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE system_user DROP git_hub_id');
        $this->addSql('ALTER TABLE system_user DROP git_lab_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE system_user ADD git_hub_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE system_user ADD git_lab_id INT DEFAULT NULL');
    }
}
