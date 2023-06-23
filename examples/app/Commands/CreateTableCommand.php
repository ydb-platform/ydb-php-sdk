<?php

namespace App\Commands;

use App\AppService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YdbPlatform\Ydb\Session;

class CreateTableCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'create';

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
        $this->setDescription('Create a table.');

        $this->addArgument('table', InputArgument::REQUIRED, 'The table name.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table_name = $input->getArgument('table') ?: '';

        $columns = $this->getColumns();

        $ydb = $this->appService->initYdb();

        $ydb->table()->retrySession(function (Session $session) use ($columns, $table_name) {
            $session->createTable($table_name, $columns, 'id');
        },true);

        $output->writeln('Table ' . $table_name . ' has been created.');

        $ydb->table()->retryTransaction(function (Session $session) use ($columns, $table_name) {
                $session->query('upsert into `' . $table_name . '` (`' . implode('`, `', array_keys($columns)) . '`) values (' . implode('), (', $this->getData()) . ');');
        },true);
        $output->writeln('Table ' . $table_name . ' has been populated with some data.');

        return Command::SUCCESS;
    }

    /**
     * @return array
     */
    protected function getColumns()
    {
        return [
            'id'         => 'UINT64',
            'name'       => 'UTF8',
            'type'       => 'STRING',
            'status'     => 'UINT32',
            'created_at' => 'DATETIME',
        ];
    }

    /**
     * @return array
     */
    protected function getData()
    {
        return [
            '1, "Item 1", "basic", 1, Datetime("' . date('Y-m-d\TH:i:s\Z') . '")',
            '2, "Item 2", "medium", 1, Datetime("' . date('Y-m-d\TH:i:s\Z') . '")',
            '3, "Item 3", "basic", 2, Datetime("' . date('Y-m-d\TH:i:s\Z') . '")',
            '4, "Item 4", "medium", 2, Datetime("' . date('Y-m-d\TH:i:s\Z') . '")',
        ];
    }
}
