<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Breakmedia\Ms3Connector\Factory\EntityManagerFactory;
use Breakmedia\Ms3Connector\Service\Config\ImportConfig;
use Dotenv\Dotenv;

$dotenv = Dotenv::createUnsafeImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

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
