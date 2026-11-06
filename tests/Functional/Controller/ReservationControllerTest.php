<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Disponibilite;
use App\Entity\Formateur;
use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Entity\User;
use App\Service\JitsiService;
use App\Tests\Support\EntitesDeTestTrait;
use App\Tests\Support\Paiement\PasserellePaiementMock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReservationControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        PasserellePaiementMock::reinitialiser();
    }

    /**
     * Tunnel complet : « Payer et réserver » → page Stripe (carte 4242…, date future,
     * CVC 123) → retour sur Mentor. Renvoie la réponse du retour de Stripe.
     */
    private function reserverEtPayer(Disponibilite $disponibilite, string $token, string $jour = 'lundi'): void
    {
        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => $jour,
        ]);

        // Redirection vers la page de paiement hébergée
        self::assertResponseStatusCodeSame(303);
        $sessionId = PasserellePaiementMock::idDepuisUrl(
            (string) $this->client->getResponse()->headers->get('Location')
        );

        $reponse = static::getContainer()->get(PasserellePaiementMock::class)
            ->payer($sessionId, PasserellePaiementMock::carte('4242 4242 4242 4242', cvc: '123'));
        self::assertTrue($reponse->isSuccessful());

        // Stripe renvoie l'étudiant vers Mentor
        $this->client->request('GET', PasserellePaiementMock::demande($sessionId)->urlSucces);
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

    public function testCreationRedirigeVersLaPageDePaiementSansRienReserver(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();

        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ]);

        // 303 : le navigateur suit la redirection en GET vers Stripe Checkout
        self::assertResponseStatusCodeSame(303);
        self::assertStringStartsWith(
            PasserellePaiementMock::URL_PAGE_PAIEMENT,
            (string) $this->client->getResponse()->headers->get('Location')
        );

        // Rien n'est réservé tant que le paiement n'est pas vérifié
        self::assertSame(0, $this->nombreDeReservations());
        self::assertSame(
            ['statut' => Paiement::STATUT_EN_ATTENTE, 'montant' => '199.99'],
            (function (Paiement $paiement): array {
                return ['statut' => $paiement->getStatut(), 'montant' => $paiement->getMontant()];
            })($this->em()->getRepository(Paiement::class)->findOneBy([]))
        );
    }

    public function testCreationReserveChaqueOccurrenceDuJour(): void
    {
        /** @var Disponibilite $disponibilite */
        [$disponibilite, $token] = $this->preparerReservation();

        $this->reserverEtPayer($disponibilite, $token);

        self::assertResponseRedirects('/mes-reservations');
        self::assertSame(2, $this->nombreDeReservations());

        // Les réservations apparaissent dans « Mes réservations »
        $this->client->followRedirect();
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
            ['lundi' => 'disponible', 'mardi' => 'disponible']
        );
        $this->client->loginUser($this->creerUtilisateur());
        $token = $this->client
            ->request('GET', '/formateur/' . $disponibilite->getFormateur()->getId())
            ->filter('#btn-reserver')
            ->attr('data-csrf-token');

        foreach (['lundi', 'mardi'] as $jour) {
            $this->reserverEtPayer($disponibilite, $token, $jour);

            self::assertResponseRedirects('/mes-reservations');
        }

        // Deux lundis et un mardi entre le 19 et le 26 octobre 2026
        self::assertSame(3, $this->nombreDeReservations());
    }

    public function testCreationDesactiveLeJourReserveSurLeProfil(): void
    {
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation(),
            ['lundi' => 'disponible', 'mardi' => 'disponible']
        );
        $profil = '/formateur/' . $disponibilite->getFormateur()->getId();

        $this->client->loginUser($this->creerUtilisateur());
        $token = $this->client
            ->request('GET', $profil)
            ->filter('#btn-reserver')
            ->attr('data-csrf-token');

        $this->reserverEtPayer($disponibilite, $token);
        self::assertResponseRedirects('/mes-reservations');

        $this->client->request('GET', $profil);

        // Le jour réservé n'est plus sélectionnable, l'autre l'est toujours
        self::assertSelectorExists('.disponibilite-jour[data-jour="lundi"][disabled]');
        self::assertSelectorTextContains('.disponibilite-jour[data-jour="lundi"] .disponibilite-jour-action', 'Plus disponible');
        self::assertSelectorNotExists('.disponibilite-jour[data-jour="mardi"][disabled]');
    }

    public function testCreationAfficheUnMessageEnCasDErreurTechnique(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();

        // Stripe injoignable à la création de la session de paiement
        PasserellePaiementMock::simulerPanne();

        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ]);

        // Message lisible au lieu d'une page 500, sans détail technique
        self::assertResponseRedirects('/formateurs');
        $this->assertFlash(
            'error',
            "Le paiement n'a pas pu être lancé suite à une erreur technique. Aucun montant n'a été débité, veuillez réessayer."
        );
        self::assertSame(0, $this->nombreDeReservations());
        self::assertSame(0, $this->em()->getRepository(Paiement::class)->count([]));
    }

    public function testCreationRefuseUnJourPlusDisponible(): void
    {
        // Le bouton est désactivé sur la page, mais le POST doit aussi être refusé
        $disponibilite = $this->creerDisponibilite(
            $this->creerFormateur(),
            $this->creerFormation(),
            ['lundi' => 'disponible', 'mardi' => 'plus disponible']
        );
        $this->client->loginUser($this->creerUtilisateur());
        $token = $this->client
            ->request('GET', '/formateur/' . $disponibilite->getFormateur()->getId())
            ->filter('#btn-reserver')
            ->attr('data-csrf-token');

        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'mardi',
        ]);

        self::assertResponseRedirects('/formateurs');
        $this->assertFlash('error', "Ce jour n'est plus disponible.");
        self::assertSame(0, $this->nombreDeReservations());
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

        $this->reserverEtPayer($disponibilite, $token);
        self::assertResponseRedirects('/mes-reservations');

        // Nouvel envoi du même créneau (retour arrière) : refusé avant tout paiement
        $this->client->request('POST', '/reservation/create', [
            '_token' => $token,
            'disponibilite' => $disponibilite->getId(),
            'jour' => 'lundi',
        ]);

        self::assertResponseRedirects('/formateurs');
        $this->assertFlash('error', 'Vous avez déjà réservé ce créneau.');
        self::assertSame(2, $this->nombreDeReservations());
        self::assertSame(1, $this->em()->getRepository(Paiement::class)->count([]));
    }

    public function testCreationConfirmeLeNombreDeSeances(): void
    {
        [$disponibilite, $token] = $this->preparerReservation();

        $this->reserverEtPayer($disponibilite, $token, ' LUNDI ');

        self::assertResponseRedirects('/mes-reservations');
        $this->assertFlash(
            'success',
            'Paiement confirmé : votre réservation est enregistrée. Vous êtes inscrit à 2 séance(s) chaque Lundi.'
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

        $this->reserverEtPayer($disponibilite, $token);

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
