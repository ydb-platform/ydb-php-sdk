<?php

require __DIR__ . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__)->load();

$application = new Symfony\Component\Console\Application;

$application->add(new App\Commands\Select1Command);

$application->add(new App\Commands\WhoAmICommand);
$application->add(new App\Commands\ListEndpointsCommand);

$application->add(new App\Commands\MakeDirectoryCommand);
$application->add(new App\Commands\ListDirectoryCommand);
$application->add(new App\Commands\RemoveDirectoryCommand);

$application->add(new App\Commands\CreateTableCommand);
$application->add(new App\Commands\SelectCommand);

$application->add(new App\Commands\ReadTableCommand);

$application->add(new App\Commands\BasicExampleCommand);

$application->run();
