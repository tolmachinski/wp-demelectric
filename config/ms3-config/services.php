<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Breakmedia\Ms3Connector\Application;
use Breakmedia\Ms3Connector\Factory\EntityManagerFactory;
use Demelectric\Command\Generator;
use Demelectric\Service\PdfGenerator;
use Dotenv\Dotenv;

$dotenv = Dotenv::createUnsafeImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('Demelectric\\', __DIR__ . '/../../src/*')
        ->exclude(__DIR__ . '/../../src/{DependencyInjection,Entity,Tests,Kernel.php}');

    $services
        ->instanceof(\Symfony\Component\Console\Command\Command::class)
        ->tag('command');

    $services->set(Application::class)
        ->public()
        ->args([tagged_iterator('command')]);

    $services->set(Generator::class)
        ->public();

    $services->set(PdfGenerator::class)
        ->public();

    $services->set(\Doctrine\ORM\EntityManager::class)
        ->factory([EntityManagerFactory::class, 'createEntityManager'])
        ->args([
            array(
                'driver'   => 'pdo_mysql',
                'user'     => getenv('MS3_DB_USER'),
                'password' => getenv('MS3_DB_PASSWORD'),
                'dbname'   => getenv('MS3_DB_NAME'),
                'host'     => getenv('MS3_DB_HOST'),
                'charset'  => 'utf8',
            )
        ]);
};
