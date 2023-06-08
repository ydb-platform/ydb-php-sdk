<?php

namespace App\Commands;

use App\AppService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Select1Command extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'select1';

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
        $this->setDescription('Select 1.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \YandexCloud\Ydb\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ydb = $this->appService->initYdb();

        $table = $ydb->table();

        $result = $table->session()->query('select 1;');

        $output->writeln('Column count: ' . $result->columnCount());
        $output->writeln('Row count: ' . $result->rowCount());

        $t = new Table($output);
        $t
            ->setHeaders(array_map(function($column) {
                return $column['name'];
            }, $result->columns()))
            ->setRows($result->rows())
        ;
        $t->render();

        return Command::SUCCESS;
    }
}
