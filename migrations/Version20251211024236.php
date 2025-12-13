<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211024236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status, created_at, and updated_at fields to user table';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Add columns as NULLABLE first to avoid errors with existing data
        $this->addSql('ALTER TABLE `user` ADD status VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE `user` ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        
        // Step 2: Set default values for existing records
        $this->addSql('UPDATE `user` SET status = "active" WHERE status IS NULL');
        $this->addSql('UPDATE `user` SET created_at = NOW() WHERE created_at IS NULL');
        
        // Step 3: Make status and created_at NOT NULL
        $this->addSql('ALTER TABLE `user` MODIFY status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE `user` MODIFY created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP status');
        $this->addSql('ALTER TABLE `user` DROP created_at');
        $this->addSql('ALTER TABLE `user` DROP updated_at');
    }
}