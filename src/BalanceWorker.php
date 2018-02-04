<?php

namespace balance;

use QXS\WorkerPool\WorkerPool;
use QXS\WorkerPool\WorkerInterface;
use QXS\WorkerPool\Semaphore;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use balance\BalanceManager;
use balance\Conf;
use balance\Connector;
use balance\exceptions\UnexpectedTaskException;
use balance\exceptions\ProcessingException;
use balance\tasks\AbstractTask;
use balance\tasks\DepositTask;
use balance\tasks\WithdrawTask;
use balance\tasks\TransferTask;

class BalanceWorker implements WorkerInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Connector
     */
    protected $connector;

    /**
     * @var Semaphore
     */
    protected $semaphore;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onProcessCreate(Semaphore $semaphore)
    {
        $this->semaphore = $semaphore;
        $this->semaphore->synchronizedBegin();
        $this->logger->log(LogLevel::INFO, sprintf("[balance worker with pid %d created]", getmypid()));
        $this->connector = new Connector(
            Conf::getInstant()->getParam('db_dsn'),
            Conf::getInstant()->getParam('db_user'),
            Conf::getInstant()->getParam('db_pass')
        );
        $this->semaphore->synchronizedEnd();
    }

    public function onProcessDestroy()
    {
        $this->semaphore->synchronizedBegin();
        $this->logger->log(LogLevel::INFO, sprintf("[balance worker with pid %d destroyed]", getmypid()));
        $this->semaphore->synchronizedEnd();
    }

    public function run($timeout)
    {
        $worker = new \GearmanWorker();
        $worker->addServer(Conf::getInstant()->getParam('gearman_server'), Conf::getInstant()->getParam('gearman_port'));
        $worker->setTimeout($timeout * 1000);

        //handler function for tasks
        $handler = function (\GearmanJob $job) {
            try {
                $className = ucfirst($job->functionName()) . 'Task';
                $task = null;
                switch ($className) {
                    case 'DepositTask':
                        ['id' => $id, 'client' => $client, 'amount' => $amount] = json_decode($job->workload(), true);
                        $task = new DepositTask($id, $client, $amount, $this->connector);
                        break;
                    case 'WithdrawTask':
                        ['id' => $id, 'client' => $client, 'amount' => $amount] = json_decode($job->workload(), true);
                        $task = new WithdrawTask($id, $client, $amount, $this->connector);
                        break;
                    case 'TransferTask':
                        ['id' => $id, 'from' => $from, 'to' => $to, 'amount' => $amount] = json_decode($job->workload(), true);
                        $task = new TransferTask($id, $from, $to, $amount, $this->connector);
                        break;
                    default:
                        throw new UnexpectedTaskException('invalid incoming task');
                }
                $balanceManager = new BalanceManager($task);
                if (!$balanceManager->process()) {
                    $balanceManager->sendEvent();
                    throw new ProcessingException($balanceManager->getErrors());
                    return;
                }
                $balanceManager->sendEvent();
                $this->notifySuccess($job);
            } catch (\Exception $e) {
                $this->notifyError($job, $e);
            }
        };

        foreach (['deposit', 'withdraw', 'transfer'] as $taskType) {
            $worker->addFunction($taskType, $handler);
        }

        while ($worker->work()) {
            if ($worker->returnCode() != GEARMAN_SUCCESS) break;
        }
    }

    /**
     * Logging error and send it to client, if he is waiting for it
     * Using semaphore is neccesary to prevent troubles with parallel log writing
     * @param \GearmanJob $job
     * @param \Exception $e
     * @return void
     */
    public function notifyError(\GearmanJob $job, \Exception $e)
    {
        $error = sprintf("task: %s, data: %s, error: %s", $job->functionName(), $job->workload(), $e->getMessage());
        $this->semaphore->synchronizedBegin();
        $this->logger->log(LogLevel::ERROR, $error);
        $job->sendComplete($error);
        $this->semaphore->synchronizedEnd();
    }

    /**
     * Logging successfull task and send it to client, if he is waiting for it
     * Using semaphore is neccesary to prevent troubles with parallel log writing
     * @param \GearmanJob $job
     * @return void
     */
    public function notifySuccess(\GearmanJob $job)
    {
        $message = sprintf("task: %s, data: %s completed successfull", $job->functionName(), $job->workload());
        $this->semaphore->synchronizedBegin();
        $this->logger->log(LogLevel::INFO, $message);
        $job->sendComplete($message);
        $this->semaphore->synchronizedEnd();
    }
}
