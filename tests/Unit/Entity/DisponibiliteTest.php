<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Disponibilite;
use PHPUnit\Framework\TestCase;

class DisponibiliteTest extends TestCase
{
    public function testGetJoursSemaineTrieLesJoursDeLundiADimanche(): void
    {
        // Ordre renvoyé par MySQL : clés triées par longueur
        $disponibilite = (new Disponibilite())->setJoursSemaine([
            'jeudi' => 'disponible',
            'lundi' => 'disponible',
            'mardi' => 'plus disponible',
            'samedi' => 'disponible',
            'dimanche' => 'disponible',
            'mercredi' => 'plus disponible',
            'vendredi' => 'disponible',
        ]);

        self::assertSame(
            [
                'lundi' => 'disponible',
                'mardi' => 'plus disponible',
                'mercredi' => 'plus disponible',
                'jeudi' => 'disponible',
                'vendredi' => 'disponible',
                'samedi' => 'disponible',
                'dimanche' => 'disponible',
            ],
            $disponibilite->getJoursSemaine()
        );
    }

    public function testGetJoursSemaineConserveLesStatutsEtLesClesDOrigine(): void
    {
        // Le tri ignore la casse et les espaces, sans réécrire les clés ni les valeurs
        $disponibilite = (new Disponibilite())->setJoursSemaine([
            'DIMANCHE ' => 'Plus disponible',
            ' Lundi' => 'Disponible',
        ]);

        self::assertSame(
            [' Lundi' => 'Disponible', 'DIMANCHE ' => 'Plus disponible'],
            $disponibilite->getJoursSemaine()
        );
    }

    public function testGetJoursSemainePlaceLesJoursInconnusALaFin(): void
    {
        $disponibilite = (new Disponibilite())->setJoursSemaine([
            'funday' => 'disponible',
            'vendredi' => 'disponible',
            'holiday' => 'disponible',
            'mardi' => 'disponible',
        ]);

        self::assertSame(
            ['mardi', 'vendredi', 'funday', 'holiday'],
            array_keys($disponibilite->getJoursSemaine())
        );
    }

    public function testGetJoursSemaineLaisseLAncienFormatEnListeInchange(): void
    {
        $disponibilite = (new Disponibilite())->setJoursSemaine(['mercredi', 'lundi']);

        self::assertSame(['mercredi', 'lundi'], $disponibilite->getJoursSemaine());
    }

    public function testGetJoursSemaineSansJour(): void
    {
        self::assertSame([], (new Disponibilite())->getJoursSemaine());
    }

    public function testChangerStatutJourModifieUniquementLeJourDemande(): void
    {
        $disponibilite = (new Disponibilite())->setJoursSemaine([
            'lundi' => Disponibilite::STATUT_DISPONIBLE,
            'mardi' => Disponibilite::STATUT_DISPONIBLE,
        ]);

        $disponibilite->changerStatutJour('mardi', Disponibilite::STATUT_PLUS_DISPONIBLE);

        self::assertSame(
            ['lundi' => 'disponible', 'mardi' => 'plus disponible'],
            $disponibilite->getJoursSemaine()
        );
    }

    public function testChangerStatutJourIgnoreLaCasseEtConserveLaCle(): void
    {
        $disponibilite = (new Disponibilite())->setJoursSemaine([' Jeudi' => 'disponible']);

        $disponibilite->changerStatutJour('JEUDI ', Disponibilite::STATUT_PLUS_DISPONIBLE);

        self::assertSame([' Jeudi' => 'plus disponible'], $disponibilite->getJoursSemaine());
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function joursSansMercredi(): iterable
    {
        yield 'jour absent' => [['lundi' => 'disponible']];
        yield 'aucun jour' => [[]];
        yield 'ancien format en liste' => [['mercredi']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('joursSansMercredi')]
    public function testChangerStatutJourRefuseUnJourNonPropose(array $jours): void
    {
        $disponibilite = (new Disponibilite())->setJoursSemaine($jours);

        try {
            $disponibilite->changerStatutJour('mercredi', Disponibilite::STATUT_PLUS_DISPONIBLE);
            self::fail('Un jour non proposé aurait dû être refusé.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame("Le jour choisi n'est pas disponible.", $exception->getMessage());
        }

        // Aucun jour n'est ajouté
        self::assertSame($jours, $disponibilite->getJoursSemaine());
    }

    public function testGetJoursSemaineNeModifiePasLesDonneesEnregistrees(): void
    {
        $disponibilite = (new Disponibilite())->setJoursSemaine([
            'mercredi' => 'disponible',
            'lundi' => 'disponible',
        ]);

        $disponibilite->getJoursSemaine();

        // La propriété garde l'ordre d'origine : Doctrine ne voit aucun changement
        $propriete = new \ReflectionProperty(Disponibilite::class, 'joursSemaine');

        self::assertSame(
            ['mercredi' => 'disponible', 'lundi' => 'disponible'],
            $propriete->getValue($disponibilite)
        );
    }
}
