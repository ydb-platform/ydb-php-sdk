<?php
require_once './vendor/autoload.php';
$commands = [
    "create"    =>new \YdbPlatform\Ydb\Slo\commands\CreateCommand(),
    "run"       =>new \YdbPlatform\Ydb\Slo\commands\RunCommand(),
    "cleanup"   =>new \YdbPlatform\Ydb\Slo\commands\CleanupCommand()
];

if ($argc == 1 || !isset($commands[$argv[1]])){
    echo "Commands:\n";
    foreach ($commands as $name=>$command) {
        echo "- ".$name."\t"."- ".$command->description."\n";
    }
    exit(0);
}

if (substr($argv[2],0,4)!="grpc" && substr($argv[3],0,1)!="/"){
    // TODO
    exit(0);
}

$data = $commands[$argv[1]]->generateOptions(array_slice($argv, 4));

$command = $commands[$argv[1]];

$commands[$argv[1]]->execute($argv[2],$argv[3], $data);