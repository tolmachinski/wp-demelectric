<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Breakmedia\Ms3Connector\Service\Config\ImportConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$config = [
    ImportConfig::DEFAULT_LANGUAGE => 2,

    ImportConfig::CATEGORY_ADD_ID_TO_NAME => false,
    ImportConfig::CATEGORY_TOP_LEVEL => 1,
];

return function (ContainerConfigurator $configurator, ContainerBuilder $container) use ($config) {
    $ms3ConfigImport = $container->getParameter('ms3.config.import');
    $config = array_merge($ms3ConfigImport, $config);
    $configurator->parameters()
                 ->set('ms3.config.import', $config);
};
