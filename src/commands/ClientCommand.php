<?php

namespace balance\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Psr\Log\LogLevel;
use balance\Conf;

class ClientCommand extends Command
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected function configure()
    {
        $this->setName('client');
        $this->setDescription('Sending messages to workers from json file');
        $this->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'File with test operations', Conf::getInstant()->getParam('example_operations_file'));
        $this->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Timeout in seconds for listener without events', Conf::getInstant()->getParam('listener_timeout'));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = $input->getOption('file');
        $timeout  = (int)$input->getOption('timeout');

        $this->logger = new ConsoleLogger($output);
        if (!file_exists($filename)) {
            $this->logger->log(LogLevel::ERROR, 'operations file not exists');
            return;
        }

        $messages = json_decode(file_get_contents($filename), true);
        if (empty($messages['operations'])) {
            $this->logger->log(LogLevel::ERROR, 'operations not loaded');
            return;
        }

        $client = new \GearmanClient();
        $client->addServer(Conf::getInstant()->getParam('gearman_server'), Conf::getInstant()->getParam('gearman_port'));

        foreach ($messages['operations'] as $task) {
            //need to generate some transaction id
            $task['data']['id'] = md5(time() . $task['type'] . implode('', $task['data']) . random_bytes('10'));
            $client->doBackground($task['type'], json_encode($task['data']));
        }

        //listen for results
        $this->listenForEvents($timeout);
    }

    protected function listenForEvents($timeout)
    {
        $worker = new \GearmanWorker();
        //kill worker after timeout
        $worker->setTimeout($timeout * 1000);
        $worker->addServer(Conf::getInstant()->getParam('gearman_server'), Conf::getInstant()->getParam('gearman_port'));
        $worker->addFunction('balance_result', function (\GearmanJob $job) {
            $data = json_decode($job->workload(), true);
            if (!empty($data['error'])) {
                $this->logger->log(LogLevel::ERROR, sprintf('task %s failed: %s', $data['id'], $data['error']));
            } else {
                $this->logger->log(LogLevel::INFO, sprintf('task %s completed successfully!', $data['id']));
            }
        });

        while ($worker->work()) {
            if ($worker->returnCode() != GEARMAN_SUCCESS) break;
        }
    }
}
