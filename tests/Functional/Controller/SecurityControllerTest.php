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
    }

    private function creerCompte(bool $verifie = true): void
    {
        $user = $this->creerUtilisateur(verifie: $verifie);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::MOT_DE_PASSE));
        $this->em()->flush();
    }

    private function seConnecter(string $motDePasse): void
    {
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Se connecter', [
            'email' => 'amadou@example.test',
            'password' => $motDePasse,
        ]);
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
