<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Breakmedia\Ms3Connector\Service\Config\MapperConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$defaults = [
    'category' => [
        MapperConfig::DISPLAY_TYPE => 'both' // 'products', 'subcategories', 'both'
    ],
    'product' => [
        MapperConfig::HEIGHT => '',
        MapperConfig::WEIGHT => '',
        MapperConfig::WIDTH => '',
        MapperConfig::CATALOG_VISIBILITY => 'visible',
    ]
];

return function (ContainerConfigurator $configurator, ContainerBuilder $container) use ($defaults) {
    $ms3ConfigDefaults = $container->getParameter('ms3.config.defaults');
    $defaults = array_merge($ms3ConfigDefaults, $defaults);
    $configurator->parameters()
                 ->set('ms3.config.defaults', $defaults);
};
