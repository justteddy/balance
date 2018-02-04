<?php

namespace balance;

class Connector
{
    protected $dbh = null;

    public function __construct($dsn, $user, $password)
    {
        try {
            $opt = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->dbh = new \PDO($dsn, $user, $password, $opt);
        } catch (\PDOException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function inTransaction()
    {
        return $this->dbh->inTransaction();
    }

    public function beginTransaction()
    {
        if (!$this->inTransaction()) {
            $this->dbh->beginTransaction();
        }
    }

    public function rollback()
    {
        if ($this->inTransaction()) {
            $this->dbh->rollback();
        }
    }

    public function commit()
    {
        if ($this->inTransaction()) {
            $this->dbh->commit();
        }
    }

    public function query($sql, $params)
    {
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    public function close()
    {
        $this->dbh = null;
    }
}
