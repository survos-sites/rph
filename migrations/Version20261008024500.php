<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261008024500 extends AbstractMigration
{
    public function getDescription(): string { return 'Preserve Celtx project metadata alongside imported scripts.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE rph_script ADD project_metadata JSON DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE rph_script DROP project_metadata'); }
}
