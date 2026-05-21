<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251212051006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Foreign key and index for customer
        $fkCustomer = $this->connection->fetchAllAssociative(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer'
             AND CONSTRAINT_NAME = 'FK_81398E09B03A8386'"
        );
        if (empty($fkCustomer)) {
            $this->connection->executeStatement('ALTER TABLE customer ADD CONSTRAINT FK_81398E09B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        }
        $idxCustomer = $this->connection->fetchAllAssociative(
            "SHOW INDEX FROM customer WHERE Key_name = 'IDX_81398E09B03A8386'"
        );
        if (empty($idxCustomer)) {
            $this->connection->executeStatement('CREATE INDEX IDX_81398E09B03A8386 ON customer (created_by_id)');
        }

        // Columns, foreign key and index for order
        $colOrderCreatedBy = $this->connection->fetchAllAssociative("SHOW COLUMNS FROM `order` LIKE 'created_by_id'");
        if (empty($colOrderCreatedBy)) {
            $this->connection->executeStatement('ALTER TABLE `order` ADD created_by_id INT DEFAULT NULL');
            $this->connection->executeStatement('UPDATE `order` SET created_by_id = 3 WHERE created_by_id IS NULL');
            $this->connection->executeStatement('ALTER TABLE `order` MODIFY created_by_id INT NOT NULL');
        }
        $colOrderUpdatedAt = $this->connection->fetchAllAssociative("SHOW COLUMNS FROM `order` LIKE 'updated_at'");
        if (empty($colOrderUpdatedAt)) {
            $this->connection->executeStatement("ALTER TABLE `order` ADD updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }
        $fkOrder = $this->connection->fetchAllAssociative(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order'
             AND CONSTRAINT_NAME = 'FK_F5299398B03A8386'"
        );
        if (empty($fkOrder)) {
            $this->connection->executeStatement('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        }
        $idxOrder = $this->connection->fetchAllAssociative("SHOW INDEX FROM `order` WHERE Key_name = 'IDX_F5299398B03A8386'");
        if (empty($idxOrder)) {
            $this->connection->executeStatement('CREATE INDEX IDX_F5299398B03A8386 ON `order` (created_by_id)');
        }

        // Columns, foreign key and index for product
        $colProductCreatedBy = $this->connection->fetchAllAssociative("SHOW COLUMNS FROM product LIKE 'created_by_id'");
        if (empty($colProductCreatedBy)) {
            $this->connection->executeStatement('ALTER TABLE product ADD created_by_id INT DEFAULT NULL');
            $this->connection->executeStatement('UPDATE product SET created_by_id = 3 WHERE created_by_id IS NULL');
            $this->connection->executeStatement('ALTER TABLE product MODIFY created_by_id INT NOT NULL');
        }
        $colProductCreatedAt = $this->connection->fetchAllAssociative("SHOW COLUMNS FROM product LIKE 'created_at'");
        if (empty($colProductCreatedAt)) {
            $this->connection->executeStatement("ALTER TABLE product ADD created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
            $this->connection->executeStatement('UPDATE product SET created_at = NOW() WHERE created_at IS NULL');
            $this->connection->executeStatement("ALTER TABLE product MODIFY created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }
        $colProductUpdatedAt = $this->connection->fetchAllAssociative("SHOW COLUMNS FROM product LIKE 'updated_at'");
        if (empty($colProductUpdatedAt)) {
            $this->connection->executeStatement("ALTER TABLE product ADD updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }
        $fkProduct = $this->connection->fetchAllAssociative(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product'
             AND CONSTRAINT_NAME = 'FK_D34A04ADB03A8386'"
        );
        if (empty($fkProduct)) {
            $this->connection->executeStatement('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        }
        $idxProduct = $this->connection->fetchAllAssociative("SHOW INDEX FROM product WHERE Key_name = 'IDX_D34A04ADB03A8386'");
        if (empty($idxProduct)) {
            $this->connection->executeStatement('CREATE INDEX IDX_D34A04ADB03A8386 ON product (created_by_id)');
        }

        // Columns, foreign key and index for stock
        $colStockCreatedBy = $this->connection->fetchAllAssociative("SHOW COLUMNS FROM stock LIKE 'created_by_id'");
        if (empty($colStockCreatedBy)) {
            $this->connection->executeStatement('ALTER TABLE stock ADD created_by_id INT DEFAULT NULL');
            $this->connection->executeStatement('UPDATE stock SET created_by_id = 3 WHERE created_by_id IS NULL');
            $this->connection->executeStatement('ALTER TABLE stock MODIFY created_by_id INT NOT NULL');
        }
        $colStockCreatedAt = $this->connection->fetchAllAssociative("SHOW COLUMNS FROM stock LIKE 'created_at'");
        if (empty($colStockCreatedAt)) {
            $this->connection->executeStatement("ALTER TABLE stock ADD created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
            $this->connection->executeStatement('UPDATE stock SET created_at = NOW() WHERE created_at IS NULL');
            $this->connection->executeStatement("ALTER TABLE stock MODIFY created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }
        $colStockUpdatedAt = $this->connection->fetchAllAssociative("SHOW COLUMNS FROM stock LIKE 'updated_at'");
        if (empty($colStockUpdatedAt)) {
            $this->connection->executeStatement("ALTER TABLE stock ADD updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }
        $fkStock = $this->connection->fetchAllAssociative(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock'
             AND CONSTRAINT_NAME = 'FK_4B365660B03A8386'"
        );
        if (empty($fkStock)) {
            $this->connection->executeStatement('ALTER TABLE stock ADD CONSTRAINT FK_4B365660B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        }
        $idxStock = $this->connection->fetchAllAssociative("SHOW INDEX FROM stock WHERE Key_name = 'IDX_4B365660B03A8386'");
        if (empty($idxStock)) {
            $this->connection->executeStatement('CREATE INDEX IDX_4B365660B03A8386 ON stock (created_by_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer DROP FOREIGN KEY FK_81398E09B03A8386');
        $this->addSql('DROP INDEX IDX_81398E09B03A8386 ON customer');
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