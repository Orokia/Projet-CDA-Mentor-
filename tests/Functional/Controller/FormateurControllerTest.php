<?php
namespace App\Tests\Functional\Controller;

use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FormateurControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

    public function testIndexAfficheLesFormateurs(): void
    {
        $client = static::createClient();
        $this->creerFormateur();

        $client->request('GET', '/formateurs');

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('body', 'Aminata Diallo');
    }

    public function testProfilAfficheLesDisponibilites(): void
    {
        $client = static::createClient();
        $formateur = $this->creerFormateur();
        $this->creerDisponibilite($formateur, $this->creerFormation());

        $client->request('GET', '/formateur/' . $formateur->getId());

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Aminata Diallo');
        self::assertSelectorExists('.disponibilite-jour[data-jour="lundi"]');
    }

    public function testProfilInexistantRenvoie404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/formateur/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testProfilAvecUnIdentifiantNonNumeriqueRenvoie404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/formateur/aminata-diallo');

        self::assertResponseStatusCodeSame(404);
    }

    public function testProfilSansDisponibiliteNePresenteAucunCreneau(): void
    {
        $client = static::createClient();
        $formateur = $this->creerFormateur('youssouf.kone@exemple.fr');

        $client->request('GET', '/formateur/' . $formateur->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.disponibilite-jour');
    }

    public function testListeRefuseLaMethodePost(): void
    {
        $client = static::createClient();

        $client->request('POST', '/formateurs');

        self::assertResponseStatusCodeSame(405);
    }
}
