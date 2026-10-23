<?php
namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FormateurControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();

        $client->request('GET', '/formateurs');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body');
    }
}
