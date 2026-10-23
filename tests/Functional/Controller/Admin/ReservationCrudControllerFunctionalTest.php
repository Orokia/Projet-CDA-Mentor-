<?php

namespace App\Tests\Functional\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReservationCrudControllerFunctionalTest extends WebTestCase
{
    public function testAdminPageRespondsWithoutServerError(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin');

        self::assertLessThan(
            500,
            $client->getResponse()->getStatusCode(),
            'La page admin ne doit pas provoquer une erreur serveur.'
        );
    }

    public function testReservationIndexRespondsWithoutServerError(): void
    {
        $client = static::createClient();

        $url = '/admin?crudControllerFqcn='
            . rawurlencode(
                \App\Controller\Admin\ReservationCrudController::class
            )
            . '&crudAction=index';

        $client->request('GET', $url);

        self::assertLessThan(
            500,
            $client->getResponse()->getStatusCode(),
            'La page des réservations ne doit pas provoquer une erreur serveur.'
        );
    }
}
