<?php

namespace balance\tasks;

use balance\Connector;

class DepositTask extends AbstractTask
{
    /**
     * @var int
     */
    protected $client;

    public function __construct(string $id, int $client, int $amount, Connector $connector)
    {
        $this->id        = $id;
        $this->client    = $client;
        $this->amount    = $amount;
        $this->connector = $connector;
    }

    public function process()
    {
        if ($this->isEmptyAmount()) {
            $this->addError(sprintf('can not process 0 summ operation', $client));
            return false;
        }

        $this->connector->beginTransaction();
        $result = $this->isClientExists($this->client);
        if ($result === false) {
            $this->connector->rollback();
            $this->addError(sprintf('client %d not found', $this->client));
            return false;
        }

        //for withdraw operations
        if ($result + $this->amount < 0) {
            $this->connector->rollback();
            $this->addError(sprintf('client %d has not available balance to withdraw %d', $this->client, abs($this->amount)));
            return false;
        }

        $this->connector->query('UPDATE balance SET amount = amount + :val WHERE id = :id', ['val' => $this->amount, 'id' => $this->client]);
        $this->connector->commit();
        return true;
    }
}
