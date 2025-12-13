<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251212041916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_by_id and timestamps to product table';
    }
    public function up(Schema $schema): void
    {
        // Step 1: Add columns as NULLABLE first
        $this->addSql('ALTER TABLE product ADD created_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        
        // Step 2: Update existing records with default values (using admin user ID 3)
        $this->addSql('UPDATE product SET created_by_id = 3, created_at = NOW() WHERE created_by_id IS NULL');
        
        // Step 3: Make columns NOT NULL
        $this->addSql('ALTER TABLE product MODIFY created_by_id INT NOT NULL, MODIFY created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        
        // Step 4: Add foreign key constraint
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        
        // Step 5: Create index
        $this->addSql('CREATE INDEX IDX_D34A04ADB03A8386 ON product (created_by_id)');
    }
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADB03A8386');
        $this->addSql('DROP INDEX IDX_D34A04ADB03A8386 ON product');
        $this->addSql('ALTER TABLE product DROP created_by_id, DROP created_at, DROP updated_at');
    }
}