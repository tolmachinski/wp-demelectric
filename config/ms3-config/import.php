<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Breakmedia\Ms3Connector\Service\Config\ImportConfig;
use Dotenv\Dotenv;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$dotenv = Dotenv::createUnsafeImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

$config = [
    ImportConfig::DEFAULT_LANGUAGE => 1,
    ImportConfig::CATEGORY_ADD_ID_TO_NAME => false,
    ImportConfig::CATEGORY_TOP_LEVEL => 1,
    ImportConfig::PATH_TO_IMAGES => getenv('MS3_PATH_TO_IMAGES'),
    ImportConfig::IMAGES_DIR_IN_NAME => "Graphics",
    ImportConfig::IMAGES_DIR_NAME => "Images",
    ImportConfig::LANGUAGES_HASHMAP => [
        1 => "de",
        2 => "fr",
    ],
    ImportConfig::SKIP_ATTRIBUTES => [
        'Test'
    ],
    ImportConfig::PRODUCT_RELATION_TYPE_HASHMAP => [
        "upsells" => 1,
        "cross-sells" => 2,
    ],
    ImportConfig::ATTRIBUTES_VALUES_MAPPER => [
        'StockStatus' => [
            1 => 'red',
            2 => 'orange',
            3 => 'green',
            4 => 'green',
            5 => 'orange',
            7 => 'yellow',
        ]
    ]
];

return function (ContainerConfigurator $configurator, ContainerBuilder $container) use ($config) {
    $ms3ConfigImport = $container->getParameter('ms3.config.import');
    $config = array_merge($ms3ConfigImport, $config);
    $configurator->parameters()
                 ->set('ms3.config.import', $config);
};
