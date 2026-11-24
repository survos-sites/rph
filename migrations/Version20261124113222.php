<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124113222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Character appearance, pose specification and generated pose assets';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rph_character ADD marking VARCHAR(32) DEFAULT \'new\' NOT NULL');
        $this->addSql('ALTER TABLE rph_character ADD appearance JSON DEFAULT \'{}\' NOT NULL');
        $this->addSql('ALTER TABLE rph_character ADD poses JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE rph_character ADD pose_assets JSON DEFAULT \'{}\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rph_character DROP marking');
        $this->addSql('ALTER TABLE rph_character DROP appearance');
        $this->addSql('ALTER TABLE rph_character DROP poses');
        $this->addSql('ALTER TABLE rph_character DROP pose_assets');
    }
}
