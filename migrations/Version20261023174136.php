<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration de base : remplace les 16 migrations historiques,
 * qui ne pouvaient pas être rejouées sur une base vide.
 */
final class Version20261023174136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial complet (fusion des migrations historiques)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE disponibilite (id INT AUTO_INCREMENT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, jours_semaine JSON NOT NULL, formateur_id INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_2CBACE2F155D8F51 (formateur_id), INDEX IDX_2CBACE2F5200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE formateur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) DEFAULT NULL, experience VARCHAR(255) NOT NULL, langue VARCHAR(255) NOT NULL, localisation VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, video_presentation VARCHAR(255) NOT NULL, image VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE formateur_formation (formateur_id INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_52449B08155D8F51 (formateur_id), INDEX IDX_52449B085200282E (formation_id), PRIMARY KEY (formateur_id, formation_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, duree INT NOT NULL, prix NUMERIC(10, 2) NOT NULL, image VARCHAR(255) NOT NULL, specialite_id INT DEFAULT NULL, INDEX IDX_404021BF2195E0F0 (specialite_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE paiement (id INT AUTO_INCREMENT NOT NULL, montant NUMERIC(10, 2) NOT NULL, mode VARCHAR(50) NOT NULL, statut VARCHAR(50) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, date_reservation DATETIME NOT NULL, date_heure_debut DATETIME NOT NULL, date_heure_fin DATETIME NOT NULL, statut VARCHAR(50) NOT NULL, lien_visio VARCHAR(500) DEFAULT NULL, student_id INT NOT NULL, formateur_id INT NOT NULL, formation_id INT NOT NULL, paiement_id INT DEFAULT NULL, disponibilite_id INT NOT NULL, INDEX IDX_42C84955CB944F1A (student_id), INDEX IDX_42C84955155D8F51 (formateur_id), INDEX IDX_42C849555200282E (formation_id), UNIQUE INDEX UNIQ_42C849552A4C4478 (paiement_id), INDEX IDX_42C849552B9D6493 (disponibilite_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE specialite (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, titre VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE specialite_formateur (specialite_id INT NOT NULL, formateur_id INT NOT NULL, INDEX IDX_41206D412195E0F0 (specialite_id), INDEX IDX_41206D41155D8F51 (formateur_id), PRIMARY KEY (specialite_id, formateur_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE student (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, photo VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE student_formation (student_id INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_272EE03FCB944F1A (student_id), INDEX IDX_272EE03F5200282E (formation_id), PRIMARY KEY (student_id, formation_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, nom_utilisateur VARCHAR(255) NOT NULL, prenom_utilisateur VARCHAR(255) NOT NULL, photo_utilisateur VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, is_verified TINYINT NOT NULL, student_id INT DEFAULT NULL, formateur_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649CB944F1A (student_id), UNIQUE INDEX UNIQ_8D93D649155D8F51 (formateur_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE disponibilite ADD CONSTRAINT FK_2CBACE2F155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id)');
        $this->addSql('ALTER TABLE disponibilite ADD CONSTRAINT FK_2CBACE2F5200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE formateur_formation ADD CONSTRAINT FK_52449B08155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formateur_formation ADD CONSTRAINT FK_52449B085200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BF2195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849555200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849552A4C4478 FOREIGN KEY (paiement_id) REFERENCES paiement (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849552B9D6493 FOREIGN KEY (disponibilite_id) REFERENCES disponibilite (id)');
        $this->addSql('ALTER TABLE specialite_formateur ADD CONSTRAINT FK_41206D412195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE specialite_formateur ADD CONSTRAINT FK_41206D41155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_formation ADD CONSTRAINT FK_272EE03FCB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_formation ADD CONSTRAINT FK_272EE03F5200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE disponibilite DROP FOREIGN KEY FK_2CBACE2F155D8F51');
        $this->addSql('ALTER TABLE disponibilite DROP FOREIGN KEY FK_2CBACE2F5200282E');
        $this->addSql('ALTER TABLE formateur_formation DROP FOREIGN KEY FK_52449B08155D8F51');
        $this->addSql('ALTER TABLE formateur_formation DROP FOREIGN KEY FK_52449B085200282E');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BF2195E0F0');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955CB944F1A');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955155D8F51');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849555200282E');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849552A4C4478');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849552B9D6493');
        $this->addSql('ALTER TABLE specialite_formateur DROP FOREIGN KEY FK_41206D412195E0F0');
        $this->addSql('ALTER TABLE specialite_formateur DROP FOREIGN KEY FK_41206D41155D8F51');
        $this->addSql('ALTER TABLE student_formation DROP FOREIGN KEY FK_272EE03FCB944F1A');
        $this->addSql('ALTER TABLE student_formation DROP FOREIGN KEY FK_272EE03F5200282E');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649CB944F1A');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649155D8F51');
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
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
