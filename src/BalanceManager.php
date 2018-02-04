<?php

namespace balance;

use balance\tasks\AbstractTask;
use balance\Conf;

class BalanceManager
{
    /**
     * @var AbstractTask
     */
    protected $task;

    public function __construct(AbstractTask $task)
    {
        $this->task = $task;
    }

    /**
     * Process task
     *
     * @return boolean
     */
    public function process(): bool
    {
        return $this->task->process();
    }

    /**
     * Returns task errors
     *
     * @return string
     */
    public function getErrors(): string
    {
        return implode(', ', $this->task->getErrors());
    }

    /**
     * Send event with result to the queue
     *
     * @return void
     */
    public function sendEvent()
    {
        $client = new \GearmanClient();
        $client->addServer(Conf::getInstant()->getParam('gearman_server'), Conf::getInstant()->getParam('gearman_port'));

        $data   = ['id' => $this->task->getId()];
        $errors = $this->getErrors();
        if (!empty($errors)) {
            $data['result'] = 'success';
            $data['error']  = $errors;
        }

        $client->doBackground('balance_result', json_encode($data));
    }
}
