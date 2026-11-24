<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124101933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add script and scene review states and persistent artwork generation requests';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rph_asset (id VARCHAR(32) NOT NULL, marking VARCHAR(32) NOT NULL, quality VARCHAR(16) NOT NULL, uri TEXT DEFAULT NULL, error TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, requested_quality VARCHAR(16) NOT NULL, description TEXT NOT NULL, prompt TEXT NOT NULL, specification JSONB NOT NULL, scene_id VARCHAR(160) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_15007B8C166053B4 ON rph_asset (scene_id)');
        $this->addSql('ALTER TABLE rph_asset ADD CONSTRAINT FK_15007B8C166053B4 FOREIGN KEY (scene_id) REFERENCES rph_scene (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rph_scene ADD marking VARCHAR(32) DEFAULT \'draft\' NOT NULL');
        $this->addSql('ALTER TABLE rph_script ADD marking VARCHAR(32) DEFAULT \'imported\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rph_asset DROP CONSTRAINT FK_15007B8C166053B4');
        $this->addSql('DROP TABLE rph_asset');
        $this->addSql('ALTER TABLE rph_scene DROP marking');
        $this->addSql('ALTER TABLE rph_script DROP marking');
    }
}
