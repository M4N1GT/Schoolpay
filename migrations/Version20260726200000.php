<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deduplication reference to notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD reference VARCHAR(120) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_notification_reference ON notification (reference)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_notification_reference');
        $this->addSql('ALTER TABLE notification DROP reference');
    }
}
