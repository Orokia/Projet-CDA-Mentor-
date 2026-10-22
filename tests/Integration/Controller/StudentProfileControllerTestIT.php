<?php
namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StudentProfileControllerTestIT extends WebTestCase
{
    public function testIndexRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request('GET', '/student/profil');

        self::assertResponseRedirects('/login');
    }
}
