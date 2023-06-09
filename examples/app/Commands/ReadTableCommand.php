<?php

namespace App\Commands;

use App\AppService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReadTableCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'read-table';

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
        $this->setDescription('Read table example.');

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

        $ydb = $this->appService->initYdb();

        $table = $ydb->table();

        $description = $table->describeTable($table_name);

        $columns = [];

        foreach ($description['columns'] ?? [] as $column)
        {
            $columns[] = $column['name'];
        }

        if ( ! $columns)
        {
            throw new \Exception('Failed to get columns for table ' . $table_name);
        }

        $options = [
            'row_limit' => 10,
            'ordered' => true,
            'key_range' => [
                'gte' => $table->tuple('uint64', 10),
                'lte' => $table->tuple('uint64', 30),

                // other options:

                // multi-column primary key:
                // 'lte' => $table->tuple('uint64,uin64', [30, 10]), // two-column primary key

                // operators variant:
                // 'less' => $table->tuple('uint64', 30), // less than
                // 'lt' => $table->tuple('uint64', 30), // less than
                // '<' => $table->tuple('uint64', 30), // less than

                // 'less_or_equal' => $table->tuple('uint64', 30), // less than or equal
                // 'lte' => $table->tuple('uint64', 30), // less than or equal
                // '<=' => $table->tuple('uint64', 30), // less than or equal

                // 'greater' => $table->tuple('uint64', 30), // greater than
                // 'gt' => $table->tuple('uint64', 30), // greater than
                // '>' => $table->tuple('uint64', 30), // greater than

                // 'greater_or_equal' => $table->tuple('uint64', 30), // greater than or equal
                // 'gte' => $table->tuple('uint64', 30), // greater than or equal
                // '>=' => $table->tuple('uint64', 30), // greater than or equal
            ],
        ];

        foreach ($table->readTable($table_name, $columns, $options) as $i => $result)
        {
            $output->writeln('Portion #' . ($i + 1));
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
        }

        return Command::SUCCESS;
    }

}
