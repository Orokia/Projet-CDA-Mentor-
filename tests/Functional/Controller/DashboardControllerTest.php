<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Reservation;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tableau de bord étudiant (/tableau-de-bord), espace formateur
 * (/espace-formateur) et guide de style (/design-system).
 */
final class DashboardControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testTableauDeBordRedirigeUnVisiteurVersLaConnexion(): void
    {
        $this->client->request('GET', '/tableau-de-bord');

        self::assertResponseRedirects('/login');
    }

    public function testTableauDeBordAfficheLaProchaineSeance(): void
    {
        $user = $this->creerUtilisateur();
        $formateur = $this->creerFormateur();
        $formation = $this->creerFormation('Développement Symfony avancé');

        $reservation = (new Reservation())
            ->setDateReservation(new \DateTime())
            ->setDateHeureDebut(new \DateTime('+3 days 09:00'))
            ->setDateHeureFin(new \DateTime('+3 days 10:00'))
            ->setStatut('confirmée')
            ->setLienVisio('https://meet.jit.si/Mentor-Reservation-test')
            ->setFormation($formation)
            ->setFormateur($formateur)
            ->setDisponibilite($this->creerDisponibilite($formateur, $formation));
        $user->getStudent()->addReservation($reservation);
        $this->em()->persist($reservation);
        $this->em()->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/tableau-de-bord');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bonjour Amadou');
        self::assertSelectorTextContains('.m-next', 'Développement Symfony avancé');
        self::assertSelectorExists('.m-next a[href="https://meet.jit.si/Mentor-Reservation-test"]');
        self::assertSelectorCount(1, '.m-course-row');
    }

    public function testTableauDeBordSansReservationInviteAuCatalogue(): void
    {
        $this->client->loginUser($this->creerUtilisateur());
        $this->client->request('GET', '/tableau-de-bord');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.m-next');
        self::assertSelectorTextContains('.m-empty', 'Aucune formation en cours');
    }

    public function testTableauDeBordRedirigeUnFormateurVersSonEspace(): void
    {
        $user = $this->creerUtilisateur(avecProfilEtudiant: false);
        $user->setFormateur($this->creerFormateur());
        $this->em()->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/tableau-de-bord');

        self::assertResponseRedirects('/espace-formateur');
    }

    public function testEspaceFormateurAfficheSesFormationsEtDisponibilites(): void
    {
        $formateur = $this->creerFormateur();
        $formation = $this->creerFormation('Initiation à la cybersécurité');
        $formateur->addFormation($formation);
        $this->creerDisponibilite($formateur, $formation);

        $user = $this->creerUtilisateur(avecProfilEtudiant: false);
        $user->setFormateur($formateur);
        $this->em()->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/espace-formateur');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bonjour Aminata');
        self::assertSelectorTextContains('.m-course-row', 'Initiation à la cybersécurité');
        self::assertSelectorCount(1, '.m-table tbody tr');
    }

    public function testEspaceFormateurRefuseUnCompteSansProfilFormateur(): void
    {
        $this->client->loginUser($this->creerUtilisateur());
        $this->client->request('GET', '/espace-formateur');

        self::assertResponseStatusCodeSame(403);
    }

    public function testGuideDeStyleEstDisponibleHorsProduction(): void
    {
        $this->client->request('GET', '/design-system');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Design system');
        self::assertSelectorExists('.formation-card');
    }
}
