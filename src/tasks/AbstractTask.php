<?php

namespace balance\tasks;

use balance\Connector;
use balance\exceptions\UnexpectedTaskException;
use balance\exceptions\ProcessingException;

abstract class AbstractTask
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var int
     */
    protected $amount;

    /**
     * @var Connector
     */
    protected $connector;

    /**
     * Task errors
     * @var array
     */
    protected $errors = [];

    abstract public function process();

    public function getId()
    {
        return $this->id;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function addError(string $err)
    {
        $this->errors[] = $err;
    }

    /**
     * If sended task has 0 amount
     *
     * @return boolean
     */
    public function isEmptyAmount()
    {
        if ($this->amount == 0) {
            return true;
        }
        return false;
    }

    /**
     * Check if clients exists. Locking row with "SELECT ... FOR UPDATE"
     * Useful in transaction only, to release rows after lock
     *
     * @param integer ...$client
     * @return mixed false if not found or current balance of each client
     */
    public function isClientExists(int ...$client)
    {
        if (!$this->connector->inTransaction()) {
            throw ProcessingException('processing works not in transaction mode');
        }

        $in     = str_repeat('?,', count($client) - 1) . '?';
        $stmt   = $this->connector->query("SELECT amount FROM balance WHERE id IN ($in) FOR UPDATE", $client);
        $result = $stmt->fetchAll();

        if (empty($result)) {
            return false;
        }

        //serching for 1 client
        if (count($client) == 1) {
            return $result[0]['amount'];
        }

        //serching for few clients
        if (count($client) == count($result)) {
            return array_map(function ($row) {
                return $row['amount'];
            }, $result);
        }

        return false;
    }
}
