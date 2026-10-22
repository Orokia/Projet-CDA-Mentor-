<?php
namespace App\Tests\Functional\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FormateurControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $metadata = $entityManager
            ->getMetadataFactory()
            ->getAllMetadata();

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema($metadata);

        $client->request('GET', '/formateurs');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body');
    }
}
