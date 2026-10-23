<?php

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReservationControllerIntegrationTest extends WebTestCase
{
    public function testIndexRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request('GET', '/mes-reservations');

        self::assertResponseRedirects('/login');
    }
}
