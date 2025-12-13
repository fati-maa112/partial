<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251205123749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_customer DROP FOREIGN KEY FK_60C16CB88D9F6D38');
        $this->addSql('ALTER TABLE order_customer DROP FOREIGN KEY FK_60C16CB89395C3F3');
        $this->addSql('DROP TABLE order_customer');
        $this->addSql('ALTER TABLE `order` ADD customer_id INT NOT NULL, DROP customer_name');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993989395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('CREATE INDEX IDX_F52993989395C3F3 ON `order` (customer_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE order_customer (order_id INT NOT NULL, customer_id INT NOT NULL, INDEX IDX_60C16CB89395C3F3 (customer_id), INDEX IDX_60C16CB88D9F6D38 (order_id), PRIMARY KEY(order_id, customer_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE order_customer ADD CONSTRAINT FK_60C16CB88D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_customer ADD CONSTRAINT FK_60C16CB89395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        $this->addSql('DROP INDEX IDX_F52993989395C3F3 ON `order`');
        $this->addSql('ALTER TABLE `order` ADD customer_name VARCHAR(100) NOT NULL, DROP customer_id');
    }
}
