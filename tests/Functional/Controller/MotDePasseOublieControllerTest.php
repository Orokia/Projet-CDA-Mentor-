<?php

namespace App\Tests\Functional\Controller;

use App\Entity\DemandeReinitialisationMotDePasse;
use App\Entity\User;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * « Mot de passe oublié » : e-mail avec un lien à usage unique, puis nouveau mot de passe.
 */
final class MotDePasseOublieControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

    private const EMAIL = 'amadou@example.test';
    private const ANCIEN_MOT_DE_PASSE = 'AncienMotDePasse1!';
    private const NOUVEAU_MOT_DE_PASSE = 'NouveauMotDePasse2?';
    private const MESSAGE_ENVOI = 'Si un compte existe pour cette adresse, vous allez recevoir un e-mail';
    private const MESSAGE_LIEN_INVALIDE = 'Ce lien de réinitialisation est invalide ou a expiré.';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    private function creerCompte(bool $verifie = true): User
    {
        $user = $this->creerUtilisateur(self::EMAIL, verifie: $verifie);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::ANCIEN_MOT_DE_PASSE));
        $this->em()->flush();

        return $user;
    }

    private function demanderLien(string $email = self::EMAIL): void
    {
        $this->client->request('GET', '/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', [
            'demande_reinitialisation_mot_de_passe[email]' => $email,
        ]);
    }

    /**
     * Lien contenu dans le dernier e-mail envoyé.
     */
    private function lienDeLEmail(): string
    {
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame(1, preg_match('#href="(http://localhost/mot-de-passe-oublie/reinitialiser/[^"]+)"#', (string) $email->getHtmlBody(), $correspondance));

        return $correspondance[1];
    }

    private function choisirMotDePasse(string $motDePasse, ?string $confirmation = null): void
    {
        $this->client->submitForm('Enregistrer le mot de passe', [
            'nouveau_mot_de_passe[plainPassword][first]' => $motDePasse,
            'nouveau_mot_de_passe[plainPassword][second]' => $confirmation ?? $motDePasse,
        ]);
    }

    private function motDePasseValide(string $motDePasse): bool
    {
        $this->em()->clear();
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => self::EMAIL]);

        return static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, $motDePasse);
    }

    public function testLaPageDeConnexionProposeLeLien(): void
    {
        $this->client->request('GET', '/login');

        self::assertSelectorExists('a[href="/mot-de-passe-oublie"]');
    }

    public function testParcoursComplet(): void
    {
        $this->creerCompte();

        $this->demanderLien();

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'to', self::EMAIL);
        self::assertEmailSubjectContains($email, 'Réinitialisation de votre mot de passe');
        self::assertEmailHtmlBodyContains($email, 'expire dans 60 minutes');
        $lien = $this->lienDeLEmail();

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', self::MESSAGE_ENVOI);

        // Le jeton quitte l'URL dès l'ouverture du lien
        $this->client->request('GET', $lien);
        self::assertResponseRedirects('/mot-de-passe-oublie/reinitialiser');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $this->choisirMotDePasse(self::NOUVEAU_MOT_DE_PASSE);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Votre mot de passe a été modifié.');

        self::assertTrue($this->motDePasseValide(self::NOUVEAU_MOT_DE_PASSE));
        self::assertFalse($this->motDePasseValide(self::ANCIEN_MOT_DE_PASSE));

        // Connexion avec le nouveau mot de passe
        $this->client->submitForm('Se connecter', ['email' => self::EMAIL, 'password' => self::NOUVEAU_MOT_DE_PASSE]);
        self::assertResponseRedirects();
        self::assertStringNotContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testLeLienNeSertQuUneFois(): void
    {
        $this->creerCompte();
        $this->demanderLien();
        $lien = $this->lienDeLEmail();

        $this->client->request('GET', $lien);
        $this->client->followRedirect();
        $this->choisirMotDePasse(self::NOUVEAU_MOT_DE_PASSE);

        $this->client->request('GET', $lien);
        $this->client->followRedirect();

        self::assertResponseRedirects('/mot-de-passe-oublie');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', self::MESSAGE_LIEN_INVALIDE);
        self::assertSame(0, $this->em()->getRepository(DemandeReinitialisationMotDePasse::class)->count([]));
    }

    /**
     * Même réponse que le compte existe ou non : les adresses inscrites ne se devinent pas.
     */
    public function testAdresseInconnueMemeMessageEtAucunEmail(): void
    {
        $this->demanderLien('inconnu@example.test');

        self::assertEmailCount(0);
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', self::MESSAGE_ENVOI);
    }

    public function testUneSecondeDemandeRapprocheeNEnvoiePasDeNouvelEmail(): void
    {
        $this->creerCompte();
        $this->demanderLien();
        self::assertEmailCount(1);

        $this->demanderLien();

        self::assertEmailCount(0);
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', self::MESSAGE_ENVOI);
    }

    public function testAdresseMalFormeeRefusee(): void
    {
        $this->demanderLien('pas-une-adresse');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Veuillez saisir une adresse e-mail valide.');
        self::assertEmailCount(0);
    }

    public function testMotDePasseTropFaibleRefuse(): void
    {
        $this->creerCompte();
        $this->demanderLien();
        $this->client->request('GET', $this->lienDeLEmail());
        $this->client->followRedirect();

        $this->choisirMotDePasse('faible');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Le mot de passe doit contenir au moins 8 caractères.');
        self::assertTrue($this->motDePasseValide(self::ANCIEN_MOT_DE_PASSE));
    }

    public function testLesDeuxSaisiesDoiventCorrespondre(): void
    {
        $this->creerCompte();
        $this->demanderLien();
        $this->client->request('GET', $this->lienDeLEmail());
        $this->client->followRedirect();

        $this->choisirMotDePasse(self::NOUVEAU_MOT_DE_PASSE, 'AutreMotDePasse3!');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Les deux mots de passe ne correspondent pas.');
        self::assertTrue($this->motDePasseValide(self::ANCIEN_MOT_DE_PASSE));
    }

    public function testLienInvalideRefuse(): void
    {
        $this->client->request('GET', '/mot-de-passe-oublie/reinitialiser/jeton-invente-0123456789abcdef0123456789');
        $this->client->followRedirect();

        self::assertResponseRedirects('/mot-de-passe-oublie');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', self::MESSAGE_LIEN_INVALIDE);
    }

    public function testFormulaireInaccessibleSansLien(): void
    {
        $this->client->request('GET', '/mot-de-passe-oublie/reinitialiser');

        self::assertResponseRedirects('/mot-de-passe-oublie');
    }

    /**
     * Le lien a été reçu sur l'adresse du compte : elle est de fait confirmée.
     */
    public function testConfirmeLAdresseDUnCompteNonVerifie(): void
    {
        $this->creerCompte(verifie: false);
        $this->demanderLien();
        $this->client->request('GET', $this->lienDeLEmail());
        $this->client->followRedirect();

        $this->choisirMotDePasse(self::NOUVEAU_MOT_DE_PASSE);

        $this->em()->clear();
        self::assertTrue($this->em()->getRepository(User::class)->findOneBy(['email' => self::EMAIL])->isVerified());
    }
}
