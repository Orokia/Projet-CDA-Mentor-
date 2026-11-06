<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StudentProfileControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

    /** @var list<string> */
    private array $fichiersTeleverses = [];

    public function testModificationDuProfilSynchroniseLeCompte(): void
    {
        $client = static::createClient();
        $user = $this->creerUtilisateur();
        $client->loginUser($user);

        $client->request('GET', '/student/profil');
        self::assertResponseIsSuccessful();

        $client->submitForm('Enregistrer les modifications', [
            'student_profile[prenom]' => 'Awa',
            'student_profile[nom]' => 'Traoré',
            'student_profile[email]' => 'awa@example.test',
        ]);

        self::assertResponseRedirects('/student/profil');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Ton profil a été mis à jour.');

        $this->em()->clear();
        $enBase = $this->em()->find(User::class, $user->getId());

        self::assertSame('Awa', $enBase->getPrenomUtilisateur());
        self::assertSame('Traoré', $enBase->getNomUtilisateur());
        // Le champ e-mail est désactivé : une valeur forcée dans la requête est ignorée
        self::assertSame('amadou@example.test', $enBase->getEmail());
        self::assertSame('Awa', $enBase->getStudent()->getPrenom());
    }

    public function testCompteSansProfilEtudiantRenvoie404(): void
    {
        $client = static::createClient();
        $client->loginUser($this->creerUtilisateur(avecProfilEtudiant: false));

        $client->request('GET', '/student/profil');

        self::assertResponseStatusCodeSame(404);
    }

    public function testVisiteurAnonymeEstRedirigeVersLaConnexion(): void
    {
        $client = static::createClient();

        $client->request('GET', '/student/profil');

        self::assertResponseRedirects('/login');
    }

    public function testProfilIncompletEstRefuseAvecUn422(): void
    {
        $client = static::createClient();
        $user = $this->creerUtilisateur();
        $client->loginUser($user);

        $client->request('GET', '/student/profil');
        $client->submitForm('Enregistrer les modifications', [
            'student_profile[prenom]' => '',
            'student_profile[nom]' => 'Traoré',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form');

        // Rien n'est enregistré
        $this->em()->clear();
        self::assertSame('Amadou', $this->em()->find(User::class, $user->getId())->getPrenomUtilisateur());
    }

    public function testPhotoDeProfilEstTeleverseeEtEnregistree(): void
    {
        $client = static::createClient();
        $user = $this->creerUtilisateur();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/student/profil');
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $form['student_profile[photoFile]']->upload($this->creerImagePng());
        $client->submit($form);

        self::assertResponseRedirects('/student/profil');

        $this->em()->clear();
        $photo = $this->em()->find(User::class, $user->getId())->getPhotoUtilisateur();

        // Nom nettoyé par le slugger, suffixe aléatoire, extension devinée
        self::assertMatchesRegularExpression('/^portrait-amadou-[0-9a-f]{12}\.png$/', $photo);
        $this->fichiersTeleverses[] = $this->dossierUploads() . '/' . $photo;
        self::assertFileExists($this->dossierUploads() . '/' . $photo);
    }

    public function testFichierQuiNEstPasUneImageEstRefuse(): void
    {
        $client = static::createClient();
        $client->loginUser($this->creerUtilisateur());

        $fichier = $this->cheminTemporaire('cv-amadou.pdf');
        file_put_contents($fichier, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF");

        $crawler = $client->request('GET', '/student/profil');
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $form['student_profile[photoFile]']->upload($fichier);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Choisis une image JPG, PNG ou WEBP.');
    }

    public function testNomComposeUniquementDEspacesEstRefuse(): void
    {
        $client = static::createClient();
        $user = $this->creerUtilisateur();
        $client->loginUser($user);

        $client->request('GET', '/student/profil');
        $client->submitForm('Enregistrer les modifications', [
            'student_profile[prenom]' => 'Awa',
            'student_profile[nom]' => '   ',
        ]);

        self::assertResponseStatusCodeSame(422);

        $this->em()->clear();
        self::assertSame('Diallo', $this->em()->find(User::class, $user->getId())->getNomUtilisateur());
    }

    public function testPhotoDePlusDe2MoEstRefusee(): void
    {
        $client = static::createClient();
        $user = $this->creerUtilisateur();
        $client->loginUser($user);

        // PNG valide suivi de données : 2 Mo + 1 octet (limite haute + 1)
        $fichier = $this->creerImagePng();
        file_put_contents($fichier, str_repeat("\0", 2_000_001 - filesize($fichier)), FILE_APPEND);

        $crawler = $client->request('GET', '/student/profil');
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $form['student_profile[photoFile]']->upload($fichier);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);

        $this->em()->clear();
        self::assertNull($this->em()->find(User::class, $user->getId())->getPhotoUtilisateur());
    }

    public function testFichierDeguiseEnImageEstRefuse(): void
    {
        $client = static::createClient();
        $client->loginUser($this->creerUtilisateur());

        // Extension .png mais contenu texte : le type MIME réel est vérifié
        $fichier = $this->cheminTemporaire('photo.png');
        file_put_contents($fichier, '<?php echo "pas une image";');

        $crawler = $client->request('GET', '/student/profil');
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $form['student_profile[photoFile]']->upload($fichier);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Choisis une image JPG, PNG ou WEBP.');
    }

    protected function tearDown(): void
    {
        // Les photos téléversées par les tests ne doivent pas rester dans public/
        foreach ($this->fichiersTeleverses as $fichier) {
            if (is_file($fichier)) {
                unlink($fichier);
            }
        }

        parent::tearDown();
    }

    private function dossierUploads(): string
    {
        return static::getContainer()->getParameter('kernel.project_dir') . '/public/assets/images/utilisateurs';
    }

    private function cheminTemporaire(string $nom): string
    {
        $dossier = sys_get_temp_dir() . '/mentor-tests-' . bin2hex(random_bytes(4));
        mkdir($dossier);

        return $dossier . '/' . $nom;
    }

    // Image PNG valide de 1 × 1 pixel
    private function creerImagePng(): string
    {
        $fichier = $this->cheminTemporaire('portrait amadou.png');
        file_put_contents($fichier, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        return $fichier;
    }
}
