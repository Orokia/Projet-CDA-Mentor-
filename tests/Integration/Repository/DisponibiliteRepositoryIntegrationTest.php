<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Disponibilite;
use App\Repository\DisponibiliteRepository;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Requête DQL écrite à la main : vérifiée sur une vraie base.
 */
final class DisponibiliteRepositoryIntegrationTest extends KernelTestCase
{
    use EntitesDeTestTrait;

    private DisponibiliteRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->repository = static::getContainer()->get(DisponibiliteRepository::class);
    }

    public function testFindByFormationAndFormateurFiltreEtTrieParDate(): void
    {
        $formateur = $this->creerFormateur('aminata.diallo@exemple.fr');
        $autreFormateur = $this->creerFormateur('youssouf.kone@exemple.fr');
        $symfony = $this->creerFormation('Développement Symfony avancé');
        $react = $this->creerFormation('React et TypeScript');

        // Créées dans le désordre pour vérifier le tri
        $decembre = $this->creerDisponibilite($formateur, $symfony, ['jeudi'], '2026-12-03', '2026-12-17');
        $novembre = $this->creerDisponibilite($formateur, $symfony, ['mardi'], '2026-11-03', '2026-11-24');

        // Ni la même formation, ni le même formateur : exclues
        $this->creerDisponibilite($formateur, $react, ['lundi'], '2026-11-02', '2026-11-30');
        $this->creerDisponibilite($autreFormateur, $symfony, ['lundi'], '2026-11-02', '2026-11-30');

        $resultat = $this->repository->findByFormationAndFormateur(
            $symfony->getId(),
            $formateur->getId()
        );

        self::assertSame(
            [$novembre->getId(), $decembre->getId()],
            array_map(static fn (Disponibilite $d): int => $d->getId(), $resultat)
        );
    }

    public function testFindByFormationAndFormateurSansCreneauRetourneUneListeVide(): void
    {
        $formateur = $this->creerFormateur('aminata.diallo@exemple.fr');
        $formation = $this->creerFormation('Développement Symfony avancé');

        self::assertSame(
            [],
            $this->repository->findByFormationAndFormateur($formation->getId(), $formateur->getId())
        );
    }
}
