<?php
namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FormationControllerIntegrationTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();

        $client->request('GET', '/formations');

        self::assertResponseIsSuccessful();
    }
}
