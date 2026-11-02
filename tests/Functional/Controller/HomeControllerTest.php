<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Support\EntitesDeTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    use EntitesDeTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testAccueilAfficheLesFormationsDisponibles(): void
    {
        $symfony = $this->creerFormation('Développement Symfony avancé');
        $this->creerFormation('Initiation à la cybersécurité');

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        self::assertPageTitleSame('Mentor - Plateforme de formation');
        self::assertSelectorTextContains('h1', 'Apprenez avec les meilleurs formateurs.');

        self::assertSelectorCount(2, '.formation-card');
        self::assertAnySelectorTextContains('.formation-title', 'Développement Symfony avancé');
        self::assertAnySelectorTextContains('.formation-title', 'Initiation à la cybersécurité');
        self::assertAnySelectorTextContains('.formation-price', '199,99 €');
        self::assertSelectorExists(sprintf('a.formation-discover[href="/formations/%d"]', $symfony->getId()));
    }

    public function testAccueilSansFormationAfficheUnMessage(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(0, '.formation-card');
        self::assertSelectorTextContains('.formation-empty', 'Aucune formation disponible');
    }

    public function testAccueilProposeLesLiensDeNavigation(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->selectLink('Découvrir les formations'));
        self::assertSelectorExists('a[href="/formations"]');
        self::assertSelectorExists('a[href="/formateurs"]');
    }

    public function testAccueilRefuseLaMethodePost(): void
    {
        $this->client->request('POST', '/');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('Allow', 'GET');
    }
}
