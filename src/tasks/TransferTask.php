<?php

namespace balance\tasks;

use balance\Connector;

class TransferTask extends AbstractTask
{
    /**
     * @var int
     */
    protected $from;

    /**
     * @var int
     */
    protected $to;

    public function __construct(string $id, int $from, int $to, int $amount, Connector $connector)
    {
        $this->id        = $id;
        $this->from      = $from;
        $this->to        = $to;
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
        $result = $this->isClientExists($this->from, $this->to);
        if ($result === false) {
            $this->connector->rollback();
            $this->addError(sprintf('clients not found - %d, %d', $this->from, $this->to));
            return false;
        }

        [$fromAmount, $toAmount] = $result;
        if ($fromAmount - $this->amount < 0) {
            $this->connector->rollback();
            $this->addError(sprintf(
                    'client %d has not available balance to transfer %d to client %d',
                    $this->from,
                    $this->amount,
                    $this->to
                )
            );
            return false;
        }

        $this->connector->query('UPDATE balance SET amount = amount - :val WHERE id = :id', ['val' => $this->amount, 'id' => $this->from]);
        $this->connector->query('UPDATE balance SET amount = amount + :val WHERE id = :id', ['val' => $this->amount, 'id' => $this->to]);
        $this->connector->commit();
        return true;
    }
}
