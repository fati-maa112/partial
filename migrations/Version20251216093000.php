<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251216093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product relation (product_id) to order_item and FK/index';
    }

    public function up(Schema $schema): void
    {
        // this up() migration adds the product_id column and FK to product
        $this->addSql('ALTER TABLE `order_item` ADD product_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_ORDER_ITEM_PRODUCT_ID ON `order_item` (product_id)');
        $this->addSql('ALTER TABLE `order_item` ADD CONSTRAINT FK_ORDER_ITEM_PRODUCT_ID FOREIGN KEY (product_id) REFERENCES `product` (id)');
    }

    public function down(Schema $schema): void
    {
        // revert
        $this->addSql('ALTER TABLE `order_item` DROP FOREIGN KEY FK_ORDER_ITEM_PRODUCT_ID');
        $this->addSql('DROP INDEX IDX_ORDER_ITEM_PRODUCT_ID ON `order_item`');
        $this->addSql('ALTER TABLE `order_item` DROP product_id');
    }
}
