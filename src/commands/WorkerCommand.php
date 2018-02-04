<?php

namespace balance\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Logger\ConsoleLogger;
use QXS\WorkerPool\WorkerPool;
use balance\BalanceWorker;
use balance\Connector;
use balance\Conf;

class WorkerCommand extends Command
{
    protected function configure()
    {
        $this->setName('worker');
        $this->setDescription('Create necessary count of worker instances');
        $this->addOption('count', 'c', InputOption::VALUE_REQUIRED, 'Worker count', Conf::getInstant()->getParam('worker_count'));
        $this->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Timeout in seconds for worker without tasks', Conf::getInstant()->getParam('worker_timeout'));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkMysqlIsReady($output);
        $timeout      = (int)$input->getOption('timeout');
        $workersCount = (int)$input->getOption('count');

        //init pool of workers
        $wp = new WorkerPool();
        $wp->setWorkerPoolSize($workersCount);
        $wp->setParentProcessTitleFormat('balanceWorkerMain');
        $wp->setChildProcessTitleFormat('balanceWorker #%i% [%state%]');
        $wp->create(new BalanceWorker(new ConsoleLogger($output)));

        for($i = 1; $i <= $workersCount; $i++) {
            $wp->run($timeout);
        }

        $wp->waitForAllWorkers();
    }

    /**
     * @link https://hub.docker.com/_/mysql/
     * No connections until MySQL init completes
     * If there is no database initialized when the container starts, then a default database will be created.
     * While this is the expected * behavior, this means that it will not accept incoming connections until such
     * initialization completes. This may cause issues when using automation tools, such as docker-compose, which
     * start several containers simultaneously.
     */
    protected function checkMysqlIsReady($output)
    {
        $tries = 0;
        while ($tries < 3) {
            try {
                $tries++;
                $connector = new Connector(
                    Conf::getInstant()->getParam('db_dsn'),
                    Conf::getInstant()->getParam('db_user'),
                    Conf::getInstant()->getParam('db_pass')
                );
                $connector->close();
                unset($connector);
                return true;
            } catch (\Exception $e) {
                $output->writeln("waiting for mysql starts");
                sleep(2);
                continue;
            }
        }
        return false;
    }
}
