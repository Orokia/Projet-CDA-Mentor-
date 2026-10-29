<?php
namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testRegister(): void
    {
        // Affichage du formulaire d'inscription
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        // Soumission du formulaire
        $this->client->submitForm('Valider', [
            'registration_form[nomUtilisateur]' => 'Diallo',
            'registration_form[prenomUtilisateur]' => 'Amadou',
            'registration_form[email]' => 'amadou@example.test',
            'registration_form[plainPassword]' => 'MotDePasse123!',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseRedirects('/');

        // Le compte et son profil étudiant existent, l'e-mail n'est pas encore vérifié
        $user = static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => 'amadou@example.test']);

        self::assertNotNull($user);
        self::assertFalse($user->isVerified());
        self::assertNotNull($user->getStudent());

        // L'e-mail de confirmation a été envoyé
        self::assertEmailCount(1);

        /** @var TemplatedEmail $email */
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'to', 'amadou@example.test');
        self::assertEmailHtmlBodyContains($email, 'This link will expire');

        // Clic sur le lien de confirmation
        $messageBody = $email->getHtmlBody();
        self::assertIsString($messageBody);
        self::assertMatchesRegularExpression('#http://localhost/verify/email[^"]+#', $messageBody);
        preg_match('#(http://localhost/verify/email[^"]+)"#', $messageBody, $lien);

        $this->client->loginUser($user);
        $this->client->request('GET', $lien[1]);

        self::assertResponseRedirects('/register');

        $user = static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => 'amadou@example.test']);

        self::assertTrue($user->isVerified());
    }

    /**
     * @param array<string, string|bool> $champsModifies
     */
    #[DataProvider('saisiesInvalides')]
    public function testInscriptionInvalideEstRefuseeAvecUn422(array $champsModifies, string $messageAttendu): void
    {
        $this->client->request('GET', '/register');

        $this->client->submitForm('Valider', $champsModifies + [
            'registration_form[nomUtilisateur]' => 'Ouattara',
            'registration_form[prenomUtilisateur]' => 'Salimata',
            'registration_form[email]' => 'salimata.ouattara@exemple.fr',
            'registration_form[plainPassword]' => 'Harmattan2026!',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', $messageAttendu);

        // Aucun compte créé, aucun e-mail envoyé
        self::assertSame(0, static::getContainer()->get(UserRepository::class)->count([]));
        self::assertEmailCount(0);
    }

    public static function saisiesInvalides(): iterable
    {
        yield 'nom vide' => [
            ['registration_form[nomUtilisateur]' => ''],
            'Veuillez renseigner votre nom.',
        ];
        yield 'prénom d\'une lettre' => [
            ['registration_form[prenomUtilisateur]' => 'S'],
            'Le prénom doit contenir au moins 2 caractères.',
        ];
        yield 'e-mail mal formé' => [
            ['registration_form[email]' => 'salimata.ouattara@'],
            'Veuillez saisir une adresse e-mail valide.',
        ];
        yield 'mot de passe trop court' => [
            ['registration_form[plainPassword]' => 'Ha26!'],
            'Le mot de passe doit contenir au moins 8 caractères.',
        ];
        yield 'mot de passe sans majuscule' => [
            ['registration_form[plainPassword]' => 'harmattan2026!'],
            'Le mot de passe doit contenir au moins une lettre majuscule.',
        ];
        yield 'mot de passe sans chiffre' => [
            ['registration_form[plainPassword]' => 'Harmattan!!'],
            'Le mot de passe doit contenir au moins un chiffre.',
        ];
        yield 'mot de passe sans caractère spécial' => [
            ['registration_form[plainPassword]' => 'Harmattan2026'],
            'Le mot de passe doit contenir au moins un caractère spécial.',
        ];
        yield 'conditions non acceptées' => [
            ['registration_form[agreeTerms]' => false],
            'Vous devez accepter les conditions générales.',
        ];
    }

    public function testInscriptionAvecUnEmailDejaUtiliseEstRefusee(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Valider', [
            'registration_form[nomUtilisateur]' => 'Ouattara',
            'registration_form[prenomUtilisateur]' => 'Salimata',
            'registration_form[email]' => 'salimata.ouattara@exemple.fr',
            'registration_form[plainPassword]' => 'Harmattan2026!',
            'registration_form[agreeTerms]' => true,
        ]);
        self::assertResponseRedirects('/');

        // Même adresse e-mail pour un second compte
        $this->client->request('GET', '/register');
        $this->client->submitForm('Valider', [
            'registration_form[nomUtilisateur]' => 'Ouattara',
            'registration_form[prenomUtilisateur]' => 'Karim',
            'registration_form[email]' => 'salimata.ouattara@exemple.fr',
            'registration_form[plainPassword]' => 'Harmattan2026!',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'There is already an account with this email');
        self::assertSame(1, static::getContainer()->get(UserRepository::class)->count([]));
    }

    public function testVerificationSansIdentifiantRedirigeVersLInscription(): void
    {
        $this->client->request('GET', '/verify/email');

        self::assertResponseRedirects('/register');
    }

    public function testVerificationDUnCompteInexistantRedirigeVersLInscription(): void
    {
        $this->client->request('GET', '/verify/email?id=999999');

        self::assertResponseRedirects('/register');
    }

    public function testVerificationAvecUnLienFalsifieEstRefusee(): void
    {
        $user = (new User())
            ->setEmail('salimata.ouattara@exemple.fr')
            ->setNomUtilisateur('Ouattara')
            ->setPrenomUtilisateur('Salimata')
            ->setPassword('hash-non-utilise');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        $this->client->request('GET', sprintf(
            '/verify/email?expires=%d&id=%d&signature=signature-falsifiee&token=jeton-falsifie',
            time() + 3600,
            $user->getId()
        ));

        self::assertResponseRedirects('/register');
        self::assertNotEmpty(
            $this->client->getRequest()->getSession()->getFlashBag()->get('verify_email_error')
        );

        $em->clear();
        self::assertFalse($em->find(User::class, $user->getId())->isVerified());
    }
}
