<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tunnel de paiement Stripe Checkout :
 * - paiement : devise, session Stripe, référence du paiement, créneau demandé
 *   (disponibilité + jour), étudiant, dates de création et de paiement ;
 * - reservation.paiement : un paiement couvre désormais plusieurs réservations
 *   (une par séance), l'index unique devient un index simple.
 */
final class Version20261105154207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paiement Stripe Checkout : session, référence, créneau ; un paiement pour plusieurs réservations';
    }

    // MySQL valide implicitement la transaction à chaque ALTER TABLE
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // date_creation : ajoutée nullable, renseignée pour les paiements existants, puis obligatoire
        $this->addSql('ALTER TABLE paiement ADD devise VARCHAR(3) DEFAULT \'eur\' NOT NULL, ADD session_checkout_id VARCHAR(255) DEFAULT NULL, ADD reference_paiement VARCHAR(255) DEFAULT NULL, ADD jour VARCHAR(20) DEFAULT NULL, ADD date_creation DATETIME DEFAULT NULL, ADD date_paiement DATETIME DEFAULT NULL, ADD student_id INT DEFAULT NULL, ADD disponibilite_id INT DEFAULT NULL');
        $this->addSql('UPDATE paiement SET date_creation = NOW() WHERE date_creation IS NULL');
        $this->addSql('ALTER TABLE paiement MODIFY date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1ECB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E2B9D6493 FOREIGN KEY (disponibilite_id) REFERENCES disponibilite (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B1DC7A1EE8349953 ON paiement (session_checkout_id)');
        $this->addSql('CREATE INDEX IDX_B1DC7A1ECB944F1A ON paiement (student_id)');
        $this->addSql('CREATE INDEX IDX_B1DC7A1E2B9D6493 ON paiement (disponibilite_id)');
        $this->addSql('CREATE INDEX idx_paiement_creneau ON paiement (disponibilite_id, jour, statut)');
        $this->addSql('ALTER TABLE reservation DROP INDEX UNIQ_42C849552A4C4478, ADD INDEX IDX_42C849552A4C4478 (paiement_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1ECB944F1A');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E2B9D6493');
        $this->addSql('DROP INDEX UNIQ_B1DC7A1EE8349953 ON paiement');
        $this->addSql('DROP INDEX IDX_B1DC7A1ECB944F1A ON paiement');
        $this->addSql('DROP INDEX IDX_B1DC7A1E2B9D6493 ON paiement');
        $this->addSql('DROP INDEX idx_paiement_creneau ON paiement');
        $this->addSql('ALTER TABLE paiement DROP devise, DROP session_checkout_id, DROP reference_paiement, DROP jour, DROP date_creation, DROP date_paiement, DROP student_id, DROP disponibilite_id');
        $this->addSql('ALTER TABLE reservation DROP INDEX IDX_42C849552A4C4478, ADD UNIQUE INDEX UNIQ_42C849552A4C4478 (paiement_id)');
    }
}
