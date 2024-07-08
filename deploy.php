<?php

namespace Deployer;

require 'recipe/laravel.php';
require 'contrib/php-fpm.php';
require 'contrib/yarn.php';

set('shared_dirs', ['storage']);
set('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/uploads',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/framework/media-library/',
    'storage/framework/media-library/temp',
    'storage/logs',
]);
set('application', 'unawork');
set('repository', 'git@bitbucket.org:greenice/unawork.git');
set('php_fpm_version', '8.2');
set('http_user', 'www-data');
set('worker_name', 'app-worker');
set('keep_releases', 2);
set('remote_user', 'developer');
set('deploy_path', '/var/www/unawork');

host('dev')
    ->setSshArguments([
        '-o StrictHostKeyChecking=no',
        '-o UserKnownHostsFile=/dev/null'
    ])
    ->set('composer_options', '--verbose --prefer-dist --no-progress --no-interaction --optimize-autoloader')
    ->set('hostname', '92.243.26.166')
    ->set('branch', 'dev');

task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'artisan:storage:link',
//    'artisan:view:cache',
    'artisan:config:cache',
//    'yarn:install',
//    'yarn:build',
    'artisan:migrate',
    'deploy:publish',
//    'supervisor:restart'
]);



task('yarn:build', function () {
    cd('{{release_path}}');
    run('{{bin/yarn}} build');
});

task('supervisor:restart', function () {
    run('echo "" | sudo -S /usr/bin/supervisorctl restart {{worker_name}}:*');
});

after('deploy:failed', 'deploy:unlock');
