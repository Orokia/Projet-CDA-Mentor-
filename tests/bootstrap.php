<?php

use App\Kernel;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// L'environnement vient de TEST_APP_ENV (voir phpunit.dist.xml) et non de APP_ENV,
// que le conteneur Docker fixe à "dev".
$testEnv = getenv('TEST_APP_ENV') ?: 'test';
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $testEnv;
putenv('APP_ENV='.$testEnv);

if (method_exists(Dotenv::class, 'bootEnv')) {
    // "ci" est traité comme "test" : .env.local n'est pas chargé.
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env', 'dev', ['test', 'ci']);
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Recrée le schéma une seule fois, avant toute la suite.
// Les tests ne doivent pas exécuter de DDL : DAMA isole chaque test par transaction.
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$entityManager = $kernel->getContainer()->get('doctrine')->getManager();

// Garde-fou : ne jamais supprimer le schéma d'une vraie base MySQL.
if ($entityManager->getConnection()->getDatabasePlatform() instanceof SQLitePlatform) {
    $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
    $schemaTool = new SchemaTool($entityManager);
    $schemaTool->dropDatabase();
    $schemaTool->createSchema($metadata);
}

$kernel->shutdown();
