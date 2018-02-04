<?php

namespace balance;

class Conf
{
    public $conf;
    private static $instant;

    private function __construct()
    {
        $file = 'config.json';
        if (!file_exists($file)) {
            $this->confError('config file not found');
        }

        $this->conf = $this->jsonEncodeCritical(file_get_contents($file));
    }

    private function __clone(){}
    private function __wakeup(){}

    public static function getInstant()
    {
        if (empty(self::$instant)) {
            self::$instant = new self();
        }

        return self::$instant;
    }

    public function getParam($name)
    {
        if (empty($this->conf->$name)) {
            $this->confError($name . ' not set');
        }

        return $this->conf->$name;
    }

    protected function jsonEncodeCritical($data)
    {
        $ret = json_decode($data);
        if ($ret === null && json_last_error()) {
            $this->confError('config not valid');
        }
        return $ret;
    }

    protected function confError($text)
    {
        echo sprintf("Error: %s\n", $text);
        exit;
    }
}
