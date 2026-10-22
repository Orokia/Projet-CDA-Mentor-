<?php
namespace App\Tests\Integration\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FormateurControllerTestIT extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager
            ->getMetadataFactory()
            ->getAllMetadata();

        $schemaTool->createSchema($metadata);

        $client->request('GET', '/formateurs');

        self::assertResponseIsSuccessful();
    }
}
