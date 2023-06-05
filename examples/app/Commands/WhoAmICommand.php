<?php

namespace App\Commands;

use App\AppService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WhoAmICommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'whoami';

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
        $this->setDescription('Get the WhoAmI information.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ydb = $this->appService->initYdb();

        $discovery = $ydb->discovery();

        $result = $discovery->whoAmI();

        $output->writeln($result);

        return Command::SUCCESS;
    }
}
