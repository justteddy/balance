<?php

namespace balance\tasks;

use balance\Connector;

class WithdrawTask extends DepositTask
{
    public function __construct(string $id, int $client, int $amount, Connector $connector)
    {
        parent::__construct($id, $client, $amount, $connector);
        $this->amount = -$this->amount;
    }
}
