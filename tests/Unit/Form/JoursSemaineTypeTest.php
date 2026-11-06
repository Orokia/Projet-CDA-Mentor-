<?php

namespace App\Tests\Unit\Form;

use App\Form\JoursSemaineType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Test\TypeTestCase;

// TypeTestCase crée lui-même un mock du dispatcher d'événements, sans attente
#[AllowMockObjectsWithoutExpectations]
class JoursSemaineTypeTest extends TypeTestCase
{
    public function testLeFormulaireProposeLesSeptJoursDansLOrdre(): void
    {
        $form = $this->factory->create(JoursSemaineType::class);

        self::assertSame(
            ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
            array_keys($form->all())
        );
    }

    public function testLaSoumissionNeGardeQueLesJoursProposes(): void
    {
        $form = $this->factory->create(JoursSemaineType::class);

        $form->submit([
            'lundi' => 'disponible',
            'mardi' => 'plus disponible',
            // « Non proposé »
            'mercredi' => '',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame(
            ['lundi' => 'disponible', 'mardi' => 'plus disponible'],
            $form->getData()
        );
    }

    public function testUnStatutInconnuEstRefuse(): void
    {
        $form = $this->factory->create(JoursSemaineType::class);

        $form->submit(['lundi' => 'complet']);

        self::assertFalse($form->get('lundi')->isSynchronized());
    }

    public function testAucunJourProposeDonneUnTableauVide(): void
    {
        $form = $this->factory->create(JoursSemaineType::class);

        $form->submit([]);

        self::assertSame([], $form->getData());
    }

    public function testLesDonneesExistantesSontNormaliseesALAffichage(): void
    {
        $form = $this->factory->create(JoursSemaineType::class, [
            ' Mardi ' => 'Plus Disponible',
            'lundi' => 'disponible',
            // Ignorés : jour inconnu et ancien format en liste
            'funday' => 'disponible',
            0 => 'jeudi',
        ]);

        self::assertSame('disponible', $form->get('lundi')->getData());
        self::assertSame('plus disponible', $form->get('mardi')->getData());
        self::assertNull($form->get('jeudi')->getData());
    }

    public function testFormaterAfficheLeJourEtSonStatut(): void
    {
        self::assertSame(
            'Lundi (disponible), Mardi (plus disponible)',
            JoursSemaineType::formater(['lundi' => 'disponible', 'mardi' => 'plus disponible'])
        );
        self::assertSame('Aucun jour', JoursSemaineType::formater(null));
        self::assertSame('Aucun jour', JoursSemaineType::formater(['lundi']));
    }
}
