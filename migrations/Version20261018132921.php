<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261018132921 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE disponibilite ADD date_fin DATE NOT NULL, ADD jours_semaine JSON NOT NULL, ADD formation_id INT NOT NULL, DROP statut, CHANGE formateur_id formateur_id INT NOT NULL, CHANGE date date_debut DATE NOT NULL');
        $this->addSql('ALTER TABLE disponibilite ADD CONSTRAINT FK_2CBACE2F155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id)');
        $this->addSql('ALTER TABLE disponibilite ADD CONSTRAINT FK_2CBACE2F5200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('CREATE INDEX IDX_2CBACE2F5200282E ON disponibilite (formation_id)');
        $this->addSql('ALTER TABLE formateur ADD email VARCHAR(180) NOT NULL, ADD password VARCHAR(255) DEFAULT NULL, ADD image VARCHAR(255) DEFAULT NULL, DROP photo, CHANGE date_creation created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE formateur_formation ADD CONSTRAINT FK_52449B08155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formateur_formation ADD CONSTRAINT FK_52449B085200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formation CHANGE description description LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BF2195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id)');
        $this->addSql('DROP INDEX UNIQ_B1DC7A1EB83297E7 ON paiement');
        $this->addSql('ALTER TABLE paiement DROP reservation_id, CHANGE mode mode VARCHAR(50) NOT NULL, CHANGE statut statut VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE reservation ADD date_heure_debut DATETIME NOT NULL, ADD date_heure_fin DATETIME NOT NULL, ADD lien_visio VARCHAR(500) DEFAULT NULL, ADD disponibilite_id INT NOT NULL, CHANGE statut statut VARCHAR(50) NOT NULL, CHANGE student_id student_id INT NOT NULL, CHANGE formateur_id formateur_id INT NOT NULL, CHANGE formation_id formation_id INT NOT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849555200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849552A4C4478 FOREIGN KEY (paiement_id) REFERENCES paiement (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849552B9D6493 FOREIGN KEY (disponibilite_id) REFERENCES disponibilite (id)');
        $this->addSql('CREATE INDEX IDX_42C849552B9D6493 ON reservation (disponibilite_id)');
        $this->addSql('ALTER TABLE specialite_formateur ADD CONSTRAINT FK_41206D412195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE specialite_formateur ADD CONSTRAINT FK_41206D41155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student ADD email VARCHAR(180) NOT NULL');
        $this->addSql('ALTER TABLE student_formation ADD CONSTRAINT FK_272EE03FCB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_formation ADD CONSTRAINT FK_272EE03F5200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD student_id INT DEFAULT NULL, ADD formateur_id INT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649CB944F1A ON user (student_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649155D8F51 ON user (formateur_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE disponibilite DROP FOREIGN KEY FK_2CBACE2F155D8F51');
        $this->addSql('ALTER TABLE disponibilite DROP FOREIGN KEY FK_2CBACE2F5200282E');
        $this->addSql('DROP INDEX IDX_2CBACE2F5200282E ON disponibilite');
        $this->addSql('ALTER TABLE disponibilite ADD date DATE NOT NULL, ADD statut VARCHAR(255) DEFAULT NULL, DROP date_debut, DROP date_fin, DROP jours_semaine, DROP formation_id, CHANGE formateur_id formateur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE formateur ADD photo VARCHAR(255) NOT NULL, DROP email, DROP password, DROP image, CHANGE created_at date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE formateur_formation DROP FOREIGN KEY FK_52449B08155D8F51');
        $this->addSql('ALTER TABLE formateur_formation DROP FOREIGN KEY FK_52449B085200282E');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BF2195E0F0');
        $this->addSql('ALTER TABLE formation CHANGE description description VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE paiement ADD reservation_id INT DEFAULT NULL, CHANGE mode mode VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B1DC7A1EB83297E7 ON paiement (reservation_id)');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955CB944F1A');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955155D8F51');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849555200282E');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849552A4C4478');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849552B9D6493');
        $this->addSql('DROP INDEX IDX_42C849552B9D6493 ON reservation');
        $this->addSql('ALTER TABLE reservation DROP date_heure_debut, DROP date_heure_fin, DROP lien_visio, DROP disponibilite_id, CHANGE statut statut VARCHAR(255) NOT NULL, CHANGE student_id student_id INT DEFAULT NULL, CHANGE formateur_id formateur_id INT DEFAULT NULL, CHANGE formation_id formation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE specialite_formateur DROP FOREIGN KEY FK_41206D412195E0F0');
        $this->addSql('ALTER TABLE specialite_formateur DROP FOREIGN KEY FK_41206D41155D8F51');
        $this->addSql('ALTER TABLE student DROP email');
        $this->addSql('ALTER TABLE student_formation DROP FOREIGN KEY FK_272EE03FCB944F1A');
        $this->addSql('ALTER TABLE student_formation DROP FOREIGN KEY FK_272EE03F5200282E');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649CB944F1A');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649155D8F51');
        $this->addSql('DROP INDEX UNIQ_8D93D649CB944F1A ON `user`');
        $this->addSql('DROP INDEX UNIQ_8D93D649155D8F51 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP student_id, DROP formateur_id, CHANGE created_at created_at DATETIME NOT NULL');
    }
}
