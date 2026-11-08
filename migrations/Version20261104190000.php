<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration de données : disponibilite.jours_semaine passe d'une liste
 * de jours (["lundi", "mardi"]) à un objet jour => statut
 * ({"lundi": "disponible", "mardi": "disponible"}).
 *
 * Les jours de l'ancien format reçoivent le statut « disponible ».
 * Les lignes déjà au nouveau format ne sont pas modifiées.
 * Le schéma ne change pas : la colonne reste de type JSON.
 */
final class Version20261104190000 extends AbstractMigration
{
    private const STATUT_PAR_DEFAUT = 'disponible';

    public function getDescription(): string
    {
        return 'Disponibilités : jours_semaine en objet jour => statut, « disponible » par défaut';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->lireJoursSemaine() as $id => $jours) {
            // Déjà au format jour => statut
            if ([] === $jours || !array_is_list($jours)) {
                continue;
            }

            $statuts = [];

            foreach ($jours as $jour) {
                if (is_string($jour) && '' !== trim($jour)) {
                    $statuts[mb_strtolower(trim($jour))] = self::STATUT_PAR_DEFAUT;
                }
            }

            $this->mettreAJour($id, $statuts);
        }
    }

    public function down(Schema $schema): void
    {
        // Retour à la liste des jours : les statuts sont perdus
        foreach ($this->lireJoursSemaine() as $id => $jours) {
            if ([] === $jours || array_is_list($jours)) {
                continue;
            }

            $this->mettreAJour($id, array_map('strval', array_keys($jours)));
        }
    }

    /**
     * @return array<int, array<mixed>>
     */
    private function lireJoursSemaine(): array
    {
        $lignes = $this->connection->fetchAllKeyValue(
            'SELECT id, jours_semaine FROM disponibilite'
        );

        $resultat = [];

        foreach ($lignes as $id => $json) {
            $jours = json_decode((string) $json, true);
            $resultat[(int) $id] = is_array($jours) ? $jours : [];
        }

        return $resultat;
    }

    private function mettreAJour(int $id, array $jours): void
    {
        // Un tableau vide doit rester une liste JSON « [] »
        $json = json_encode($jours ?: [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->addSql(
            'UPDATE disponibilite SET jours_semaine = :jours WHERE id = :id',
            ['jours' => $json, 'id' => $id]
        );
    }
}
