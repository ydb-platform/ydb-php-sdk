<?php

namespace App\Commands;

use App\AppService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListDirectoryCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'ls';

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
        $this->setDescription('List a directory.');

        $this->addArgument('dirname', InputArgument::OPTIONAL, 'The directory name.');
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

        $result = $scheme->listDirectory($dirname);

        if (!empty($result))
        {
            $t = new Table($output);
            $t
                ->setHeaders(['name', 'type', 'owner'])
                ->setRows(array_map(function($row) {
                    return [
                        $row['name'] ?? null,
                        $row['type'] ?? null,
                        $row['owner'] ?? null,
                    ];
                }, $result))
            ;
            $t->render();
        }
        else
        {
            $output->writeln('Empty directory');
        }

        return Command::SUCCESS;
    }
}
