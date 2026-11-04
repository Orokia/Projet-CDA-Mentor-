<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Disponibilite;
use App\Entity\Formateur;
use App\Entity\Reservation;
use App\Entity\User;
use App\Service\JitsiService;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReservationControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Connecte un étudiant et récupère le jeton CSRF
     * affiché sur la page du formateur (bouton « Réserver »).
     */
    private function preparerReservation(): array
    {
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation()
        );

        $this->client->loginUser($this->creerUtilisateur());

        $crawler = $this->client->request(
            'GET',
            '/formateur/' . $disponibilite->getFormateur()->getId()
        );
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('#btn-reserver')->attr('data-csrf-token');

        return [$disponibilite, $token];
    }

    private function nombreDeReservations(): int
    {
        return $this->em()->getRepository(Reservation::class)->count([]);
    }

    public function testCreationRedirigeUnVisiteurVersLaConnexion(): void
    {
        $this->client->request('POST', '/reservation/create');

        self::assertResponseRedirects('/login');
    }

    public function testCreationReserveChaqueOccurrenceDuJour(): void
    {
        /** @var Disponibilite $disponibilite */
        [$disponibilite, $token] = $this->preparerReservation();

        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ]);

        self::assertResponseRedirects('/formateur/' . $disponibilite->getFormateur()->getId());
        self::assertSame(2, $this->nombreDeReservations());

        // Les réservations apparaissent dans « Mes réservations »
        $this->client->request('GET', '/mes-reservations');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.reservation-group');
        self::assertSelectorCount(2, '.reservation-session');
        self::assertSelectorTextContains('.reservation-group-title', 'Symfony');
    }

    public function testCreationRefuseUnJetonCsrfInvalide(): void
    {
        [$disponibilite] = $this->preparerReservation();

        $this->client->request('POST', '/reservation/create', [
            '_token' => 'jeton-invalide',
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ]);

        self::assertResponseRedirects('/formateurs');
        self::assertSame(0, $this->nombreDeReservations());
        $this->assertFlash('error', 'La session a expiré. Veuillez réessayer.');
    }

    /**
     * Les erreurs de saisie et les exceptions du service
     * deviennent un message flash, sans réservation créée.
     */
    public function testCreationAfficheLesErreursDeSaisie(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();
        $id = $disponibilite->getId();

        $cas = [
            [['disponibilite' => 'abc', 'jour' => 'lundi'], 'Veuillez sélectionner une disponibilité valide.'],
            [['disponibilite' => $id], 'Veuillez sélectionner un jour.'],
            [['disponibilite' => $id, 'jour' => 'mardi'], "Le jour choisi n'est pas disponible."],
            [['disponibilite' => 999999, 'jour' => 'lundi'], 'Disponibilité introuvable.'],
        ];

        foreach ($cas as [$parametres, $message]) {
            $this->client->request(
                'POST',
                '/reservation/create',
                ['_token' => $token] + $parametres
            );

            self::assertResponseRedirects('/formateurs');
            $this->assertFlash('error', $message);
        }

        self::assertSame(0, $this->nombreDeReservations());
    }

    /**
     * Valeurs limites des paramètres : rejetées par le contrôleur
     * ou par le service, sans réservation créée.
     */
    public function testCreationRefuseLesValeursLimitesDesParametres(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();
        $id = (string) $disponibilite->getId();

        $cas = [
            // Identifiant : zéro, négatif, décimal, notation scientifique, espaces
            [['disponibilite' => '0', 'jour' => 'lundi'], 'Veuillez sélectionner une disponibilité valide.'],
            [['disponibilite' => '-1', 'jour' => 'lundi'], 'Veuillez sélectionner une disponibilité valide.'],
            [['disponibilite' => '1.5', 'jour' => 'lundi'], 'Veuillez sélectionner une disponibilité valide.'],
            [['disponibilite' => '1e3', 'jour' => 'lundi'], 'Veuillez sélectionner une disponibilité valide.'],
            [['disponibilite' => ' ' . $id, 'jour' => 'lundi'], 'Veuillez sélectionner une disponibilité valide.'],
            [['jour' => 'lundi'], 'Veuillez sélectionner une disponibilité valide.'],
            // Identifiant numérique au-delà de PHP_INT_MAX
            [['disponibilite' => '99999999999999999999', 'jour' => 'lundi'], 'Disponibilité introuvable.'],
            // Jour : « 0 » est vide pour PHP, jour inconnu, espaces seuls
            [['disponibilite' => $id, 'jour' => '0'], 'Veuillez sélectionner un jour.'],
            [['disponibilite' => $id, 'jour' => 'monday'], "Le jour choisi n'est pas disponible."],
            [['disponibilite' => $id, 'jour' => '   '], "Le jour choisi n'est pas disponible."],
        ];

        foreach ($cas as [$parametres, $message]) {
            $this->client->request(
                'POST',
                '/reservation/create',
                ['_token' => $token] + $parametres
            );

            self::assertResponseRedirects('/formateurs');
            $this->assertFlash('error', $message);
        }

        self::assertSame(0, $this->nombreDeReservations());
    }

    public function testCreationSansJetonCsrfEstRefusee(): void
    {
        [$disponibilite] = $this->preparerReservation();

        $this->client->request('POST', '/reservation/create', [
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ]);

        self::assertResponseRedirects('/formateurs');
        $this->assertFlash('error', 'La session a expiré. Veuillez réessayer.');
        self::assertSame(0, $this->nombreDeReservations());
    }

    public function testCreationRefuseUnParametreTableauAvecUn400(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();

        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => [$disponibilite->getId()],
            'jour' => 'lundi',
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->nombreDeReservations());
    }

    public function testCreationReserveUnAutreJourDeLaMemeDisponibilite(): void
    {
        // Le contrôle de doublon porte sur le créneau, pas sur la disponibilité
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation(),
            ['lundi', 'mardi']
        );
        $this->client->loginUser($this->creerUtilisateur());
        $token = $this->client
            ->request('GET', '/formateur/' . $disponibilite->getFormateur()->getId())
            ->filter('#btn-reserver')
            ->attr('data-csrf-token');

        foreach (['lundi', 'mardi'] as $jour) {
            $this->client->request('POST', '/reservation/create', [
                '_token' => $token,
                'disponibilite' => $disponibilite->getId(),
                'jour' => $jour,
            ]);

            self::assertResponseRedirects('/formateur/' . $disponibilite->getFormateur()->getId());
        }

        // Deux lundis et un mardi entre le 19 et le 26 octobre 2026
        self::assertSame(3, $this->nombreDeReservations());
    }

    public function testVisioDUneReservationInexistanteRenvoie404(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $this->client->request('GET', '/reservation/999999/visio');

        self::assertResponseStatusCodeSame(404);
    }

    public function testVisioAvecUnIdentifiantNonNumeriqueRenvoie404(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $this->client->request('GET', '/reservation/abc/visio');

        self::assertResponseStatusCodeSame(404);
    }

    public function testVisioAvecUnIdentifiantHorsLimiteRenvoie404(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $this->client->request('GET', '/reservation/99999999999999999999/visio');

        self::assertResponseStatusCodeSame(404);
    }

    public function testVisioEnPostRenvoie405(): void
    {
        $user = $this->creerUtilisateur();
        $reservation = $this->creerReservation($user);
        $this->client->loginUser($user);

        $this->client->request('POST', '/reservation/' . $reservation->getId() . '/visio');

        self::assertResponseStatusCodeSame(405);
    }

    public function testVisioRefuseUnAutreFormateur(): void
    {
        $reservation = $this->creerReservation();

        $this->client->loginUser($this->creerCompteFormateur($this->creerFormateur('autre.formateur@example.test')));
        $this->client->request('GET', '/reservation/' . $reservation->getId() . '/visio');

        self::assertResponseStatusCodeSame(403);
    }

    public function testMesReservationsSansProfilEtudiantRenvoie404(): void
    {
        $this->client->loginUser(
            $this->creerUtilisateur(avecProfilEtudiant: false)
        );

        $this->client->request('GET', '/mes-reservations');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreationEnGetRenvoie405(): void
    {
        $this->client->request('GET', '/reservation/create');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('Allow', 'POST');
    }

    public function testCreationRefuseUnCreneauDejaReserve(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();
        $parametres = [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ];

        $this->client->request('POST', '/reservation/create', $parametres);
        self::assertResponseRedirects('/formateur/' . $disponibilite->getFormateur()->getId());

        // Deuxième envoi du même créneau (double clic, retour arrière)
        $this->client->request('POST', '/reservation/create', $parametres);

        self::assertResponseRedirects('/formateurs');
        $this->assertFlash('error', 'Vous avez déjà réservé ce créneau.');
        self::assertSame(2, $this->nombreDeReservations());
    }

    public function testCreationConfirmeLeNombreDeSeances(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();

        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => ' LUNDI ',
        ]);

        self::assertResponseRedirects();
        $this->assertFlash(
            'success',
            'Votre réservation a été créée. Vous êtes inscrit à 2 séance(s) chaque Lundi.'
        );
    }

    public function testCreationSansProfilEtudiantRenvoie404(): void
    {
        $this->client->loginUser(
            $this->creerUtilisateur('formateur.compte@exemple.fr', ['ROLE_FORMATEUR'], avecProfilEtudiant: false)
        );

        $this->client->request('POST', '/reservation/create', ['disponibilite' => '1', 'jour' => 'lundi']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->nombreDeReservations());
    }

    public function testMesReservationsRedirigeUnVisiteurVersLaConnexion(): void
    {
        $this->client->request('GET', '/mes-reservations');

        self::assertResponseRedirects('/login');
    }

    public function testMesReservationsSansReservationAfficheUnMessage(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $this->client->request('GET', '/mes-reservations');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(0, '.reservation-group');
        self::assertSelectorTextContains('h3', 'Aucune réservation');
    }

    public function testMesReservationsAfficheLeLienDeVisioconference(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();

        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ]);

        $crawler = $this->client->request('GET', '/mes-reservations');

        self::assertResponseIsSuccessful();
        $liens = $crawler->filter('.reservation-session a[href$="/visio"]');
        self::assertCount(2, $liens);
    }

    public function testMesReservationsAfficheLesSeancesDuFormateur(): void
    {
        $reservation = $this->creerReservation();

        $this->client->loginUser($this->creerCompteFormateur($reservation->getFormateur()));

        $crawler = $this->client->request('GET', '/mes-reservations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.reservation-session-trainer', 'Étudiant');
        self::assertCount(1, $crawler->filter('.reservation-session a[href$="/visio"]'));
    }

    public function testVisioRedirigeUnVisiteurVersLaConnexion(): void
    {
        $this->client->request('GET', '/reservation/' . $this->creerReservation()->getId() . '/visio');

        self::assertResponseRedirects('/login');
    }

    public function testVisioRefuseUnEtudiantQuiNaPasReserve(): void
    {
        $reservation = $this->creerReservation();

        $this->client->loginUser($this->creerUtilisateur('autre.etudiant@example.test'));
        $this->configurerJaas();
        $this->client->request('GET', '/reservation/' . $reservation->getId() . '/visio');

        self::assertResponseStatusCodeSame(403);
    }

    public function testVisioRefuseUneSeanceAnnulee(): void
    {
        $reservation = $this->creerReservation(statut: 'annulée');

        $this->client->loginUser($this->creerUtilisateur('amadou2@example.test', avecProfilEtudiant: false));
        $this->client->request('GET', '/reservation/' . $reservation->getId() . '/visio');

        self::assertResponseStatusCodeSame(403);
    }

    public function testVisioSansJaasOuvreLeLienJitsi(): void
    {
        $user = $this->creerUtilisateur();
        $reservation = $this->creerReservation($user);

        $this->client->loginUser($user);
        $this->client->request('GET', '/reservation/' . $reservation->getId() . '/visio');

        self::assertResponseRedirects($reservation->getLienVisio());
    }

    public function testVisioIntegreLaSalleDansLaPage(): void
    {
        $user = $this->creerUtilisateur();
        $reservation = $this->creerReservation($user);

        $this->client->loginUser($user);
        $this->configurerJaas();
        $crawler = $this->client->request('GET', '/reservation/' . $reservation->getId() . '/visio');

        self::assertResponseIsSuccessful();

        $salle = $crawler->filter('[data-controller="jitsi"]');
        self::assertCount(1, $salle);
        self::assertSame('8x8.vc', $salle->attr('data-jitsi-domaine-value'));
        self::assertSame(
            'vpaas-magic-cookie-test/Mentor-Reservation-1-test',
            $salle->attr('data-jitsi-salle-value')
        );
        self::assertSame(
            'https://8x8.vc/vpaas-magic-cookie-test/external_api.js',
            $salle->attr('data-jitsi-script-value')
        );
        self::assertCount(3, explode('.', $salle->attr('data-jitsi-jeton-value')));
    }

    public function testVisioAfficheUneErreurSiLeJetonNePeutPasEtreSigne(): void
    {
        $user = $this->creerUtilisateur();
        $reservation = $this->creerReservation($user);

        $this->client->loginUser($user);
        $this->configurerJaas('cle-invalide');
        $this->client->request('GET', '/reservation/' . $reservation->getId() . '/visio');

        self::assertResponseRedirects('/mes-reservations');
        $this->assertFlash('error', 'La visioconférence est momentanément indisponible.');
    }

    /**
     * Crée une réservation, pour le compte donné ou pour un autre étudiant.
     */
    private function creerReservation(?User $user = null, string $statut = 'confirmée'): Reservation
    {
        $user ??= $this->creerUtilisateur('proprietaire@example.test');
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation()
        );

        $reservation = (new Reservation())
            ->setDateReservation(new \DateTime())
            ->setDateHeureDebut(new \DateTime('2026-10-19 09:00:00'))
            ->setDateHeureFin(new \DateTime('2026-10-19 10:00:00'))
            ->setStatut($statut)
            ->setLienVisio('https://meet.jit.si/Mentor-Reservation-1-test')
            ->setStudent($user->getStudent())
            ->setFormateur($disponibilite->getFormateur())
            ->setFormation($disponibilite->getFormation())
            ->setDisponibilite($disponibilite);

        // La requête partage le gestionnaire d'entités du test :
        // le formateur doit déjà connaître sa réservation.
        $disponibilite->getFormateur()->addReservation($reservation);

        $this->em()->persist($reservation);
        $this->em()->flush();

        return $reservation;
    }

    private function creerCompteFormateur(Formateur $formateur): User
    {
        $user = $this->creerUtilisateur(
            'compte.formateur@example.test',
            ['ROLE_FORMATEUR'],
            avecProfilEtudiant: false
        );

        $user->setFormateur($formateur);
        $formateur->setCompteUtilisateur($user);
        $this->em()->flush();

        return $user;
    }

    /**
     * Remplace le service par un service configuré, pour la prochaine requête.
     * Sans contenu fourni, une vraie clé RSA est générée.
     */
    private function configurerJaas(?string $clePrivee = null): void
    {
        if ($clePrivee === null) {
            openssl_pkey_export(openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]), $clePrivee);
        }

        $chemin = tempnam(sys_get_temp_dir(), 'jaas');
        file_put_contents($chemin, $clePrivee);
        register_shutdown_function('unlink', $chemin);

        static::getContainer()->set(
            JitsiService::class,
            new JitsiService('vpaas-magic-cookie-test', 'vpaas-magic-cookie-test/abc123', $chemin)
        );
    }

    private function assertFlash(string $type, string $message): void
    {
        $flashes = $this->client->getRequest()->getSession()->getFlashBag()->get($type);

        self::assertContains($message, $flashes);
    }
}
