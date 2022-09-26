<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Breakmedia\Ms3Connector\Service\Config\MapperConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$mapping = [
    'category' => [],
    'product' => [
        'standardAttributes' => [
            MapperConfig::PRODUCT_NAME => 'description_1'
        ]
    ]
];

return function (ContainerConfigurator $configurator, ContainerBuilder $container) use ($mapping) {
    $ms3ConfigMapping = $container->getParameter('ms3.config.defaults');
    $mapping = array_merge($ms3ConfigMapping, $mapping);
    $configurator->parameters()
        ->set('ms3.config.mapping', $mapping);
};
