<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add WLED/Electrodrawer fields to storelocations table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE storelocations ADD COLUMN wled_mqtt_topic VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE storelocations ADD COLUMN wled_led_start INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE storelocations ADD COLUMN wled_led_end INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // SQLite does not support DROP COLUMN; reconstruct without WLED columns
        $this->addSql('CREATE TEMPORARY TABLE __temp__storelocations AS SELECT id, name, last_modified, datetime_added, parent_id, comment, not_selectable, storage_type_id, is_full, only_single_part, limit_to_existing_parts, id_preview_attachment, id_owner, part_owner_must_match FROM storelocations');
        $this->addSql('DROP TABLE storelocations');
        $this->addSql('CREATE TABLE storelocations AS SELECT * FROM __temp__storelocations');
        $this->addSql('DROP TABLE __temp__storelocations');
    }
}
