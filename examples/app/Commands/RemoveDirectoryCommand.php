<?php

namespace App\Commands;

use App\AppService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveDirectoryCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'rmdir';

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
        $this->setDescription('Delete a directory.');

        $this->addArgument('dirname', InputArgument::REQUIRED, 'The directory name.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dirname = $input->getArgument('dirname') ?: '';

        $ydb = $this->appService->initYdb();

        $scheme = $ydb->scheme();

        $result = $scheme->removeDirectory($dirname);

        $output->writeln(json_encode($result, 480));

        return Command::SUCCESS;
    }
}
