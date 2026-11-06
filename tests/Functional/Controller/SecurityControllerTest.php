<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Connexion par le vrai formulaire : AppCustomAuthenticator + UserChecker.
 */
final class SecurityControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

    private const MOT_DE_PASSE = 'MotDePasse123!';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Les échecs de connexion (login_throttling) sont stockés dans le cache fichier :
        // sans remise à zéro, ils s'accumuleraient d'un test et d'un lancement à l'autre
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    private function creerCompte(bool $verifie = true, string $email = 'amadou@example.test'): void
    {
        $user = $this->creerUtilisateur($email, verifie: $verifie);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::MOT_DE_PASSE));
        $this->em()->flush();
    }

    private function seConnecter(string $motDePasse, string $email = 'amadou@example.test'): void
    {
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Se connecter', [
            'email' => $email,
            'password' => $motDePasse,
        ]);
    }

    private function echouerCinqFois(string $email = 'amadou@example.test'): void
    {
        for ($tentative = 1; $tentative <= 5; ++$tentative) {
            $this->seConnecter('mauvais-mot-de-passe', $email);
            self::assertResponseRedirects('/login');
        }
    }

    public function testConnexionBloqueeApresCinqEchecs(): void
    {
        $this->creerCompte();
        $this->echouerCinqFois();

        // Même le bon mot de passe est refusé tant que la fenêtre de 15 minutes court
        $this->seConnecter(self::MOT_DE_PASSE);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Too many failed login attempts');
        self::assertNull(static::getContainer()->get('security.token_storage')->getToken());
    }

    public function testQuatreEchecsNeBloquentPasLaConnexion(): void
    {
        $this->creerCompte();

        for ($tentative = 1; $tentative <= 4; ++$tentative) {
            $this->seConnecter('mauvais-mot-de-passe');
            self::assertResponseRedirects('/login');
        }

        $this->seConnecter(self::MOT_DE_PASSE);

        self::assertResponseRedirects('/');
    }

    public function testLeBlocageNeConcernePasLesAutresComptes(): void
    {
        $this->creerCompte();
        $this->creerCompte(email: 'aissata@example.test');
        $this->echouerCinqFois();

        // La limite porte sur le couple e-mail + IP
        $this->seConnecter(self::MOT_DE_PASSE, 'aissata@example.test');

        self::assertResponseRedirects('/');
    }

    public function testConnexionReussieRedirigeVersLAccueil(): void
    {
        $this->creerCompte();

        $this->seConnecter(self::MOT_DE_PASSE);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertNotNull(
            static::getContainer()->get('security.token_storage')->getToken()?->getUser()
        );
    }

    public function testMauvaisMotDePasseAfficheUneErreur(): void
    {
        $this->creerCompte();

        $this->seConnecter('mauvais-mot-de-passe');

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
        self::assertInputValueSame('email', 'amadou@example.test');
    }

    public function testCompteNonVerifieEstRefuse(): void
    {
        $this->creerCompte(verifie: false);

        $this->seConnecter(self::MOT_DE_PASSE);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testRedirectionVersLaPageDemandeeApresConnexion(): void
    {
        $this->creerCompte();

        // Une page protégée mémorise la cible avant de rediriger vers /login
        $this->client->request('GET', '/mes-reservations');
        self::assertResponseRedirects('/login');

        $this->seConnecter(self::MOT_DE_PASSE);

        self::assertResponseRedirects('http://localhost/mes-reservations');
    }

    public function testPageDeConnexionAfficheLeFormulaire(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[type="email"][name="email"]');
        self::assertSelectorExists('form input[type="password"][name="password"]');
        self::assertSelectorExists('form input[type="hidden"][name="_csrf_token"]');
        self::assertSelectorExists('form input[type="checkbox"][name="_remember_me"]');
    }

    public function testEmailInconnuAfficheUneErreur(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            'email' => 'inconnu@exemple.fr',
            'password' => self::MOT_DE_PASSE,
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
        self::assertInputValueSame('email', 'inconnu@exemple.fr');
    }

    public function testConnexionAvecUnJetonCsrfInvalideEstRefusee(): void
    {
        $this->creerCompte();

        // Bon mot de passe, mais jeton falsifié : la connexion doit échouer
        $this->client->request('POST', '/login', [
            'email' => 'amadou@example.test',
            'password' => self::MOT_DE_PASSE,
            '_csrf_token' => 'jeton-falsifie',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
        self::assertNull(static::getContainer()->get('security.token_storage')->getToken());
    }

    public function testConnexionAvecDesChampsVidesEstRefusee(): void
    {
        $this->creerCompte();

        $this->seConnecter('');

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testMotDePasseAvecUneDifferenceDeCasseEstRefuse(): void
    {
        $this->creerCompte();

        $this->seConnecter(mb_strtolower(self::MOT_DE_PASSE));

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testDeconnexionFermeLaSession(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $this->client->request('GET', '/mes-reservations');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/logout');
        self::assertResponseRedirects('/');

        // La page protégée n'est plus accessible
        $this->client->request('GET', '/mes-reservations');
        self::assertResponseRedirects('/login');
    }
}
