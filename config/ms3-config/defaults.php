<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\ContainerBuilder;

$defaults = [
    'category' => [
        'display_type' => 'both' // 'products', 'subcategories', 'both'
    ],
    'product' => []
];

return function (ContainerConfigurator $configurator, ContainerBuilder $container) use ($defaults) {
    $ms3ConfigDefaults = $container->getParameter('ms3.config.defaults');
    $defaults = array_merge($ms3ConfigDefaults, $defaults);
    $configurator->parameters()
                 ->set('ms3.config.defaults', $defaults);
};
