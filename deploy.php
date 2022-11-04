<?php
namespace Deployer;

require 'recipe/common.php';

// Config

set('repository', 'git@bitbucket.org:breakmedia/wp-demelectric.git');
set('target', 'v1.0');

set('release_name', function () {
    return get('target');
});

add('shared_files', [
    '.env'
]);
add('shared_dirs', [
    'web/app/languages',
    'web/app/uploads',
    'web/Graphics',
    'logs',
]);
add('writable_dirs', []);

// Hosts

host('stage.demelectric.ch', 'web45.servicehoster.ch')
    ->set('remote_user', 'h175360')
    ->set('deploy_path', '~/stage.demelectric.ch');

//host('dev.demelectric.ch', 'web45.servicehoster.ch')
//    ->set('remote_user', 'h175360')
//    ->set('deploy_path', '~/dev.demelectric.ch');

host('demelectric.molbak.at', '167.235.73.12')
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '/var/www/demelectric.molbak.at');

desc('Deploys your project');
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'deploy:publish',
]);

// Hooks
after('deploy:failed', 'deploy:unlock');
