<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261123120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Keep Fountain notes ([[...]]) on script elements for voice direction.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE rph_script_element ADD notes JSON DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE rph_script_element DROP notes'); }
}
