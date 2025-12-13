<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251212002604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE record ADD created_by_id INT NOT NULL, ADD title VARCHAR(255) NOT NULL, ADD description LONGTEXT DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', DROP username, DROP action, DROP details');
        $this->addSql('ALTER TABLE record ADD CONSTRAINT FK_9B349F91B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_9B349F91B03A8386 ON record (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `record` DROP FOREIGN KEY FK_9B349F91B03A8386');
        $this->addSql('DROP INDEX IDX_9B349F91B03A8386 ON `record`');
        $this->addSql('ALTER TABLE `record` ADD username VARCHAR(180) NOT NULL, ADD action VARCHAR(50) NOT NULL, ADD details LONGTEXT NOT NULL, DROP created_by_id, DROP title, DROP description, DROP updated_at');
    }
}
