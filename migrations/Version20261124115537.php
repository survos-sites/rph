<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124115537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rph_line_speech (id VARCHAR(32) NOT NULL, marking VARCHAR(20) NOT NULL, audio_url VARCHAR(255) DEFAULT NULL, duration DOUBLE PRECISION DEFAULT NULL, speech_start DOUBLE PRECISION DEFAULT NULL, speech_duration DOUBLE PRECISION DEFAULT NULL, text TEXT NOT NULL, voice JSON NOT NULL, delivery_notes JSON NOT NULL, force BOOLEAN NOT NULL, element_id VARCHAR(192) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1C4B11771F1F2A24 ON rph_line_speech (element_id)');
        $this->addSql('ALTER TABLE rph_line_speech ADD CONSTRAINT FK_1C4B11771F1F2A24 FOREIGN KEY (element_id) REFERENCES rph_script_element (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rph_script_element ADD speech_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE rph_script_element ADD CONSTRAINT FK_92A32B26BBC049D6 FOREIGN KEY (speech_id) REFERENCES rph_line_speech (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_92A32B26BBC049D6 ON rph_script_element (speech_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rph_line_speech DROP CONSTRAINT FK_1C4B11771F1F2A24');
        $this->addSql('ALTER TABLE rph_script_element DROP CONSTRAINT FK_92A32B26BBC049D6');
        $this->addSql('DROP TABLE rph_line_speech');
        $this->addSql('DROP INDEX IDX_92A32B26BBC049D6');
        $this->addSql('ALTER TABLE rph_script_element DROP speech_id');
    }
}
