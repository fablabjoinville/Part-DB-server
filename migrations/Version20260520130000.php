<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename storelocations.wled_mqtt_topic to wled_host (WLED HTTP API migration)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE storelocations RENAME COLUMN wled_mqtt_topic TO wled_host');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE storelocations RENAME COLUMN wled_host TO wled_mqtt_topic');
    }
}
