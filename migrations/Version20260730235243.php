<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260730235243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE disponibilite (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, statut VARCHAR(255) DEFAULT NULL, formateur_id INT DEFAULT NULL, INDEX IDX_2CBACE2F155D8F51 (formateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE formateur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, photo VARCHAR(255) NOT NULL, experience VARCHAR(255) NOT NULL, langue VARCHAR(255) NOT NULL, localisation VARCHAR(255) NOT NULL, date_creation DATETIME NOT NULL, video_presentation VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE formateur_formation (formateur_id INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_52449B08155D8F51 (formateur_id), INDEX IDX_52449B085200282E (formation_id), PRIMARY KEY (formateur_id, formation_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, duree INT NOT NULL, prix NUMERIC(10, 2) NOT NULL, image VARCHAR(255) NOT NULL, specialite_id INT DEFAULT NULL, INDEX IDX_404021BF2195E0F0 (specialite_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE paiement (id INT AUTO_INCREMENT NOT NULL, montant NUMERIC(10, 2) NOT NULL, mode VARCHAR(255) NOT NULL, statut VARCHAR(255) NOT NULL, reservation_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_B1DC7A1EB83297E7 (reservation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, date_reservation DATETIME NOT NULL, statut VARCHAR(255) NOT NULL, student_id INT DEFAULT NULL, formateur_id INT DEFAULT NULL, formation_id INT DEFAULT NULL, paiement_id INT DEFAULT NULL, INDEX IDX_42C84955CB944F1A (student_id), INDEX IDX_42C84955155D8F51 (formateur_id), INDEX IDX_42C849555200282E (formation_id), UNIQUE INDEX UNIQ_42C849552A4C4478 (paiement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE specialite (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, titre VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE specialite_formateur (specialite_id INT NOT NULL, formateur_id INT NOT NULL, INDEX IDX_41206D412195E0F0 (specialite_id), INDEX IDX_41206D41155D8F51 (formateur_id), PRIMARY KEY (specialite_id, formateur_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE student (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, photo VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE student_formation (student_id INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_272EE03FCB944F1A (student_id), INDEX IDX_272EE03F5200282E (formation_id), PRIMARY KEY (student_id, formation_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE disponibilite ADD CONSTRAINT FK_2CBACE2F155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id)');
        $this->addSql('ALTER TABLE formateur_formation ADD CONSTRAINT FK_52449B08155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formateur_formation ADD CONSTRAINT FK_52449B085200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BF2195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1EB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849555200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849552A4C4478 FOREIGN KEY (paiement_id) REFERENCES paiement (id)');
        $this->addSql('ALTER TABLE specialite_formateur ADD CONSTRAINT FK_41206D412195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE specialite_formateur ADD CONSTRAINT FK_41206D41155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_formation ADD CONSTRAINT FK_272EE03FCB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_formation ADD CONSTRAINT FK_272EE03F5200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD student_id INT DEFAULT NULL, ADD formateur_id INT DEFAULT NULL, DROP statut, CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id)');
        $this->addSql('CREATE INDEX IDX_8D93D649CB944F1A ON user (student_id)');
        $this->addSql('CREATE INDEX IDX_8D93D649155D8F51 ON user (formateur_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE disponibilite DROP FOREIGN KEY FK_2CBACE2F155D8F51');
        $this->addSql('ALTER TABLE formateur_formation DROP FOREIGN KEY FK_52449B08155D8F51');
        $this->addSql('ALTER TABLE formateur_formation DROP FOREIGN KEY FK_52449B085200282E');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BF2195E0F0');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1EB83297E7');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955CB944F1A');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955155D8F51');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849555200282E');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849552A4C4478');
        $this->addSql('ALTER TABLE specialite_formateur DROP FOREIGN KEY FK_41206D412195E0F0');
        $this->addSql('ALTER TABLE specialite_formateur DROP FOREIGN KEY FK_41206D41155D8F51');
        $this->addSql('ALTER TABLE student_formation DROP FOREIGN KEY FK_272EE03FCB944F1A');
        $this->addSql('ALTER TABLE student_formation DROP FOREIGN KEY FK_272EE03F5200282E');
        $this->addSql('DROP TABLE disponibilite');
        $this->addSql('DROP TABLE formateur');
        $this->addSql('DROP TABLE formateur_formation');
        $this->addSql('DROP TABLE formation');
        $this->addSql('DROP TABLE paiement');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE specialite');
        $this->addSql('DROP TABLE specialite_formateur');
        $this->addSql('DROP TABLE student');
        $this->addSql('DROP TABLE student_formation');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649CB944F1A');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649155D8F51');
        $this->addSql('DROP INDEX IDX_8D93D649CB944F1A ON `user`');
        $this->addSql('DROP INDEX IDX_8D93D649155D8F51 ON `user`');
        $this->addSql('ALTER TABLE `user` ADD statut TINYINT NOT NULL, DROP student_id, DROP formateur_id, CHANGE created_at created_at DATETIME NOT NULL');
    }
}
