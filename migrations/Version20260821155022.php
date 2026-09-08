<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260821155022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE disponibilite ADD CONSTRAINT FK_2CBACE2F155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id)');
        $this->addSql('ALTER TABLE formateur CHANGE date_creation created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE formateur_formation ADD CONSTRAINT FK_52449B08155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formateur_formation ADD CONSTRAINT FK_52449B085200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BF2195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1EB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE reservation ADD meeting_room_id INT NOT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849555200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849552A4C4478 FOREIGN KEY (paiement_id) REFERENCES paiement (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955CCC5381E FOREIGN KEY (meeting_room_id) REFERENCES meeting_room (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_42C84955CCC5381E ON reservation (meeting_room_id)');
        $this->addSql('ALTER TABLE specialite_formateur ADD CONSTRAINT FK_41206D412195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE specialite_formateur ADD CONSTRAINT FK_41206D41155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_formation ADD CONSTRAINT FK_272EE03FCB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_formation ADD CONSTRAINT FK_272EE03F5200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649155D8F51 FOREIGN KEY (formateur_id) REFERENCES formateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE disponibilite DROP FOREIGN KEY FK_2CBACE2F155D8F51');
        $this->addSql('ALTER TABLE formateur CHANGE created_at date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE formateur_formation DROP FOREIGN KEY FK_52449B08155D8F51');
        $this->addSql('ALTER TABLE formateur_formation DROP FOREIGN KEY FK_52449B085200282E');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BF2195E0F0');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1EB83297E7');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955CB944F1A');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955155D8F51');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849555200282E');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849552A4C4478');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955CCC5381E');
        $this->addSql('DROP INDEX UNIQ_42C84955CCC5381E ON reservation');
        $this->addSql('ALTER TABLE reservation DROP meeting_room_id');
        $this->addSql('ALTER TABLE specialite_formateur DROP FOREIGN KEY FK_41206D412195E0F0');
        $this->addSql('ALTER TABLE specialite_formateur DROP FOREIGN KEY FK_41206D41155D8F51');
        $this->addSql('ALTER TABLE student_formation DROP FOREIGN KEY FK_272EE03FCB944F1A');
        $this->addSql('ALTER TABLE student_formation DROP FOREIGN KEY FK_272EE03F5200282E');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649CB944F1A');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649155D8F51');
    }
}
