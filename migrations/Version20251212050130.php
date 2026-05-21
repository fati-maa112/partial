<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251212050130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_by_id tracking to customer, order, product, and stock tables';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Add columns only if they don't exist
        $tables = [
            'customer' => ['created_by_id', 'updated_at'],
            '`order`'  => ['created_by_id', 'updated_at'],
            'product'  => ['created_by_id', 'created_at', 'updated_at'],
            'stock'    => ['created_by_id', 'created_at', 'updated_at'],
        ];

        foreach ($tables as $table => $columns) {
            $plainTable = trim($table, '`');
            foreach ($columns as $column) {
                $exists = $this->connection->fetchAllAssociative(
                    "SHOW COLUMNS FROM `$plainTable` LIKE '$column'"
                );
                if (empty($exists)) {
                    if ($column === 'created_by_id') {
                        $this->connection->executeStatement("ALTER TABLE $table ADD created_by_id INT DEFAULT NULL");
                    } elseif ($column === 'updated_at') {
                        $this->connection->executeStatement("ALTER TABLE $table ADD updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
                    } elseif ($column === 'created_at') {
                        $this->connection->executeStatement("ALTER TABLE $table ADD created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
                    }
                }
            }
        }

        // Step 2: Update existing records with default user
        $this->addSql('UPDATE customer SET created_by_id = 3 WHERE created_by_id IS NULL');
        $this->addSql('UPDATE `order` SET created_by_id = 3 WHERE created_by_id IS NULL');
        $this->addSql('UPDATE product SET created_by_id = 3, created_at = NOW() WHERE created_by_id IS NULL');
        $this->addSql('UPDATE stock SET created_by_id = 3, created_at = NOW() WHERE created_by_id IS NULL');

        // Step 3: Make required columns NOT NULL
        $this->addSql('ALTER TABLE customer MODIFY created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE `order` MODIFY created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE product MODIFY created_by_id INT NOT NULL, MODIFY created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE stock MODIFY created_by_id INT NOT NULL, MODIFY created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        // Step 4: Add foreign key constraints only if not exists
        $fks = [
            'customer' => 'FK_81398E09B03A8386',
            'order'    => 'FK_F5299398B03A8386',
            'product'  => 'FK_D34A04ADB03A8386',
            'stock'    => 'FK_4B365660B03A8386',
        ];

        foreach ($fks as $table => $fkName) {
            $exists = $this->connection->fetchAllAssociative(
                "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = '$table'
                 AND CONSTRAINT_NAME = '$fkName'"
            );
            if (empty($exists)) {
                $t = $table === 'order' ? '`order`' : $table;
                $this->connection->executeStatement("ALTER TABLE $t ADD CONSTRAINT $fkName FOREIGN KEY (created_by_id) REFERENCES `user` (id)");
            }
        }

        // Step 5: Create indexes only if not exists
        $indexes = [
            'customer' => 'IDX_81398E09B03A8386',
            'order'    => 'IDX_F5299398B03A8386',
            'product'  => 'IDX_D34A04ADB03A8386',
            'stock'    => 'IDX_4B365660B03A8386',
        ];

        foreach ($indexes as $table => $indexName) {
            $exists = $this->connection->fetchAllAssociative(
                "SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'"
            );
            if (empty($exists)) {
                $this->connection->executeStatement("CREATE INDEX $indexName ON `$table` (created_by_id)");
            }
        }
    }

    public function down(Schema $schema): void
    {
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