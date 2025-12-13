<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251212050130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_by_id tracking to customer, order, product, and stock tables';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Add columns as NULLABLE first to avoid constraint violations
        $this->addSql('ALTER TABLE customer ADD created_by_id INT DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE `order` ADD created_by_id INT DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE product ADD created_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE stock ADD created_by_id INT DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        // Step 2: Update existing records with default user (ID 1)
        // In Version20251212050130.php, change all these lines:
$this->addSql('UPDATE customer SET created_by_id = 3 WHERE created_by_id IS NULL');
$this->addSql('UPDATE `order` SET created_by_id = 3 WHERE created_by_id IS NULL');
$this->addSql('UPDATE product SET created_by_id = 3, created_at = NOW() WHERE created_by_id IS NULL');
$this->addSql('UPDATE stock SET created_by_id = 3, created_at = NOW() WHERE created_by_id IS NULL');

        // Step 3: Make required columns NOT NULL
        $this->addSql('ALTER TABLE customer MODIFY created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE `order` MODIFY created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE product MODIFY created_by_id INT NOT NULL, MODIFY created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE stock MODIFY created_by_id INT NOT NULL, MODIFY created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        // Step 4: Add foreign key constraints
        $this->addSql('ALTER TABLE customer ADD CONSTRAINT FK_81398E09B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B365660B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');

        // Step 5: Create indexes
        $this->addSql('CREATE INDEX IDX_81398E09B03A8386 ON customer (created_by_id)');
        $this->addSql('CREATE INDEX IDX_F5299398B03A8386 ON `order` (created_by_id)');
        $this->addSql('CREATE INDEX IDX_D34A04ADB03A8386 ON product (created_by_id)');
        $this->addSql('CREATE INDEX IDX_4B365660B03A8386 ON stock (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer DROP FOREIGN KEY FK_81398E09B03A8386');
        $this->addSql('DROP INDEX IDX_81398E09B03A8386 ON customer');
        $this->addSql('ALTER TABLE customer DROP created_by_id, DROP updated_at');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398B03A8386');
        $this->addSql('DROP INDEX IDX_F5299398B03A8386 ON `order`');
        $this->addSql('ALTER TABLE `order` DROP created_by_id, DROP updated_at');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADB03A8386');
        $this->addSql('DROP INDEX IDX_D34A04ADB03A8386 ON product');
        $this->addSql('ALTER TABLE product DROP created_by_id, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE stock DROP FOREIGN KEY FK_4B365660B03A8386');
        $this->addSql('DROP INDEX IDX_4B365660B03A8386 ON stock');
        $this->addSql('ALTER TABLE stock DROP created_by_id, DROP created_at, DROP updated_at');
    }
}