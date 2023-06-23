<?php

namespace App\Commands;

use App\AppService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YdbPlatform\Ydb\Ydb;

class MakeDirectoryCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'mkdir';

    /**
     * @var AppService
     */
    protected $appService;

    public function __construct()
    {
        $this->appService = new AppService;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Make a directory.');

        $this->addArgument('dirname', InputArgument::REQUIRED, 'The directory name.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dirname = $input->getArgument('dirname');

        $ydb = $this->appService->initYdb();

        $result = $ydb->retry(function (Ydb $ydb) use ($output, $dirname) {
            $scheme = $ydb->scheme();

            return $scheme->makeDirectory($dirname);
        }, true);

        $output->writeln(json_encode($result, 480));

        return Command::SUCCESS;
    }
}
