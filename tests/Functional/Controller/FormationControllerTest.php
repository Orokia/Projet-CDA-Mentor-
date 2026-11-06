<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Formation;
use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FormationControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Formation « Développement Symfony avancé » proposée par Aminata Diallo.
     */
    private function creerFormationAvecFormateur(): Formation
    {
        $formation = $this->creerFormation('Développement Symfony avancé');
        $formation->addFormateur($this->creerFormateur());
        $this->em()->flush();

        return $formation;
    }

    private function assertFlash(string $type, string $message): void
    {
        $flashes = $this->client->getRequest()->getSession()->getFlashBag()->get($type);

        self::assertContains($message, $flashes);
    }

    // ---------- GET /formations ----------

    public function testListeAfficheToutesLesFormations(): void
    {
        $this->creerFormation('Développement Symfony avancé');
        $this->creerFormation('React et TypeScript');

        $this->client->request('GET', '/formations');

        self::assertResponseIsSuccessful();
        self::assertPageTitleSame('Mentor - Nos formations');
        self::assertSelectorCount(2, '.formation-card');
        self::assertAnySelectorTextContains('.formation-title', 'React et TypeScript');
    }

    public function testListeVideAfficheUnMessage(): void
    {
        $this->client->request('GET', '/formations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Aucune formation disponible');
    }

    // ---------- GET /formations/{id} ----------

    public function testDetailAfficheLaFormationEtSesFormateurs(): void
    {
        $formation = $this->creerFormationAvecFormateur();
        $formateur = $formation->getFormateurs()->first();

        $this->client->request('GET', '/formations/' . $formation->getId());

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Développement Symfony avancé');
        self::assertSelectorTextContains('h1.formation-detail-title', 'Développement Symfony avancé');
        self::assertSelectorTextContains('.formation-detail-meta', '20 heures');
        self::assertSelectorTextContains('.formation-price', '200 €');

        // Le formulaire de choix pointe vers la bonne route et propose le formateur
        self::assertSelectorExists(sprintf(
            'form[method="post"][action="/formations/%d/choisir-formateur"]',
            $formation->getId()
        ));
        self::assertSelectorExists(sprintf('input[name="formateur"][value="%d"]', $formateur->getId()));
    }

    public function testDetailSansFormateurAfficheUnMessage(): void
    {
        $formation = $this->creerFormation('Initiation à la cybersécurité');

        $this->client->request('GET', '/formations/' . $formation->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.formation-empty', 'Aucun formateur disponible');
        self::assertSelectorNotExists('input[name="formateur"]');
    }

    public function testDetailDUneFormationInexistanteRenvoie404(): void
    {
        $this->client->request('GET', '/formations/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDetailAvecUnIdentifiantNonNumeriqueRenvoie404(): void
    {
        // requirements: ['id' => '\d+'] : sans lui, « int $id » provoquait une erreur 500
        $this->client->request('GET', '/formations/developpement-symfony');

        self::assertResponseStatusCodeSame(404);
    }

    // ---------- POST /formations/{id}/choisir-formateur ----------

    public function testChoisirUnFormateurRedirigeVersSonProfil(): void
    {
        $formation = $this->creerFormationAvecFormateur();
        $formateur = $formation->getFormateurs()->first();

        $crawler = $this->client->request('GET', '/formations/' . $formation->getId());
        $this->client->submit($crawler->selectButton('Continuer')->form([
            'formateur' => $formateur->getId(),
        ]));

        self::assertResponseRedirects('/formateur/' . $formateur->getId());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Aminata Diallo');
    }

    public function testChoisirSansFormateurAfficheUneErreur(): void
    {
        $formation = $this->creerFormationAvecFormateur();

        foreach (['', 'aminata-diallo', '-3'] as $valeur) {
            $this->client->request(
                'POST',
                sprintf('/formations/%d/choisir-formateur', $formation->getId()),
                ['formateur' => $valeur]
            );

            self::assertResponseRedirects('/formations/' . $formation->getId());
            $this->assertFlash('error', 'Veuillez sélectionner un formateur.');
        }
    }

    public function testChoisirUnFormateurInexistantRenvoie404(): void
    {
        $formation = $this->creerFormationAvecFormateur();

        $this->client->request(
            'POST',
            sprintf('/formations/%d/choisir-formateur', $formation->getId()),
            ['formateur' => '999999']
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testChoisirPourUneFormationInexistanteRenvoie404(): void
    {
        $formateur = $this->creerFormateur();

        $this->client->request(
            'POST',
            '/formations/999999/choisir-formateur',
            ['formateur' => (string) $formateur->getId()]
        );

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function identifiantsLimites(): iterable
    {
        yield 'zéro' => ['0'];
        yield 'PHP_INT_MAX' => [(string) PHP_INT_MAX];
        yield 'au-delà de PHP_INT_MAX' => ['99999999999999999999'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('identifiantsLimites')]
    public function testDetailAvecUnIdentifiantLimiteRenvoie404(string $id): void
    {
        $this->client->request('GET', '/formations/' . $id);

        self::assertResponseStatusCodeSame(404);
    }

    public function testChoisirLeFormateurZeroAfficheUneErreur(): void
    {
        // « 0 » est un identifiant numérique, mais vide pour PHP
        $formation = $this->creerFormationAvecFormateur();

        $this->client->request(
            'POST',
            sprintf('/formations/%d/choisir-formateur', $formation->getId()),
            ['formateur' => '0']
        );

        self::assertResponseRedirects('/formations/' . $formation->getId());
        $this->assertFlash('error', 'Veuillez sélectionner un formateur.');
    }

    public function testChoisirUnFormateurAvecUnIdentifiantHorsLimiteRenvoie404(): void
    {
        $formation = $this->creerFormationAvecFormateur();

        $this->client->request(
            'POST',
            sprintf('/formations/%d/choisir-formateur', $formation->getId()),
            ['formateur' => '99999999999999999999']
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testChoisirPourUneFormationHorsLimiteRenvoie404(): void
    {
        $this->client->request('POST', '/formations/99999999999999999999/choisir-formateur', ['formateur' => '1']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testChoisirUnFormateurSansParametreAfficheUneErreur(): void
    {
        $formation = $this->creerFormationAvecFormateur();

        $this->client->request('POST', sprintf('/formations/%d/choisir-formateur', $formation->getId()));

        self::assertResponseRedirects('/formations/' . $formation->getId());
        $this->assertFlash('error', 'Veuillez sélectionner un formateur.');
    }

    public function testChoisirUnFormateurEnGetRenvoie405(): void
    {
        $formation = $this->creerFormationAvecFormateur();

        $this->client->request('GET', sprintf('/formations/%d/choisir-formateur', $formation->getId()));

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('Allow', 'POST');
    }
}
