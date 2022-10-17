<?php
namespace Deployer;

require 'recipe/wordpress.php';

// Config

set('repository', 'git@bitbucket.org:breakmedia/wp-demelectric.git');

add('shared_files', []);
add('shared_dirs', []);
add('writable_dirs', []);

// Hosts

host('dev.demelectric.ch')
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '~/wp-demelectric');

host('demelectric.molbak.at')
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '~/wp-demelectric');

// Hooks

after('deploy:failed', 'deploy:unlock');
