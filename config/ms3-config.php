<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Breakmedia\Ms3Connector\Service\Import\ImporterAbstract;
use Demelectric\Application;

//ToDo: Move all configurations to bundle
//ToDo: Make config files generated on package install
return function (ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('Demelectric\\', '../src/*')
        ->exclude('../src/{DependencyInjection,Entity,Tests,Kernel.php}');

    $services->load('Breakmedia\Ms3Connector\\', '../vendor/breakmedia/ms3-connector/src/*')
        ->exclude('../vendor/breakmedia/ms3-connector/src/{DependencyInjection,Entity,Tests,Kernel.php}');

    $services
        ->instanceof(\Symfony\Component\Console\Command\Command::class)
        ->tag('command');

    $services->set(Application::class)
        ->public()
        ->args([tagged_iterator('command')]);

    $services->set(\Breakmedia\Ms3Connector\Command\Import::class)
        ->public();

    $services
        ->instanceof(ImporterAbstract::class)
        ->tag('break.ms3.importer');


    $services->set(\Breakmedia\Ms3Connector\Service\ImportManager::class)
        ->public()
        ->args([tagged_iterator('break.ms3.importer')]);
};
