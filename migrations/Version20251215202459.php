<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251215202459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Rename index only if old index exists
        $oldIndex = $this->connection->fetchAllAssociative(
            "SHOW INDEX FROM order_item WHERE Key_name = 'idx_order_item_product_id'"
        );
        if (!empty($oldIndex)) {
            $this->connection->executeStatement('ALTER TABLE order_item RENAME INDEX idx_order_item_product_id TO IDX_52EA1F094584665A');
        }
    }

    public function down(Schema $schema): void
    {
        $newIndex = $this->connection->fetchAllAssociative(
            "SHOW INDEX FROM order_item WHERE Key_name = 'IDX_52EA1F094584665A'"
        );
        if (!empty($newIndex)) {
            $this->connection->executeStatement('ALTER TABLE order_item RENAME INDEX IDX_52EA1F094584665A TO idx_order_item_product_id');
        }
    }
}