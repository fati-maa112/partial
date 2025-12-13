<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211220546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_logs ADD user_agent LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_username ON activity_logs (username)');
        $this->addSql('CREATE INDEX idx_action ON activity_logs (action)');
        $this->addSql('CREATE INDEX idx_created_at ON activity_logs (created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_username ON activity_logs');
        $this->addSql('DROP INDEX idx_action ON activity_logs');
        $this->addSql('DROP INDEX idx_created_at ON activity_logs');
        $this->addSql('ALTER TABLE activity_logs DROP user_agent');
    }
}
