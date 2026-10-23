<?php
namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
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
}
