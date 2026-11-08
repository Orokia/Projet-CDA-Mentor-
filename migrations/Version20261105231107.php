<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mot de passe oublié : demandes de réinitialisation (symfonycasts/reset-password-bundle).
 */
final class Version20261105231107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table demande_reinitialisation_mot_de_passe (mot de passe oublié)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE demande_reinitialisation_mot_de_passe (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_E4435D86A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE demande_reinitialisation_mot_de_passe ADD CONSTRAINT FK_E4435D86A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    // MySQL valide implicitement chaque instruction DDL : pas de transaction
    public function isTransactional(): bool
    {
        return false;
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_reinitialisation_mot_de_passe DROP FOREIGN KEY FK_E4435D86A76ED395');
        $this->addSql('DROP TABLE demande_reinitialisation_mot_de_passe');
    }
}
