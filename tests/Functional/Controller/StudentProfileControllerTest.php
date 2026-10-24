<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StudentProfileControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

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
}
