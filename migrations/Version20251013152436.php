<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013152436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
{
    // Create category table if not exists
    $this->addSql('CREATE TABLE IF NOT EXISTS category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    
    // Add category_id column only if it doesn't exist
    $this->addSql('ALTER TABLE product ADD COLUMN IF NOT EXISTS category_id INT DEFAULT NULL');
    
    // Add foreign key only if it doesn't exist
    $this->addSql('ALTER TABLE product ADD CONSTRAINT IF NOT EXISTS FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
    
    // Add index only if it doesn't exist
    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_D34A04AD12469DE2 ON product (category_id)');
}

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP INDEX IDX_D34A04AD12469DE2 ON product');
        $this->addSql('ALTER TABLE product DROP category_id');
    }
}
