<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124115628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rph_character ADD voice_description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE rph_character ADD voice_profile JSON DEFAULT \'{}\' NOT NULL');
        $this->addSql('ALTER TABLE rph_script_element ADD delivery TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rph_character DROP voice_description');
        $this->addSql('ALTER TABLE rph_character DROP voice_profile');
        $this->addSql('ALTER TABLE rph_script_element DROP delivery');
    }
}
