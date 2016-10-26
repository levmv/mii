<?php

namespace mii\db;

class Redis
{
    protected $host = '127.0.0.1';
    protected $port = 6379;
    protected $timeout = 0.0;
    protected $value = 0;
    protected $retry = 0;
    protected $password = '';
    protected $database = false;
    protected $connection = false;

    /**
     * Redis constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->connection = new \Redis();

        foreach ($config as $k => $v) {
            $this->$k = $v;
        }

        $this->connection->connect($this->host, $this->port, $this->timeout, $this->value, $this->retry);
        $this->connection->auth($this->password);

        if ($this->database !== false)
            $this->connection->select($this->database);
    }

    /**
     * @param string $key
     * @param string $value
     * @param bool|int|array $options
     * @return bool
     */
    public function set($key, $value, $options = false)
    {
        return $options === false
            ? $this->connection->set($key, $value)
            : $this->connection->set($key, $value, $options);
    }

    /**
     * @param string $key
     * @return bool|string
     */
    public function get($key)
    {
        return $this->connection->get($key);
    }

    /**
     * @param string $key
     * @return int
     */
    public function incr($key)
    {
        return $this->connection->incr($key);
    }

    /**
     * @param string $key
     * @return int
     */
    public function decr($key)
    {
        return $this->connection->decr($key);
    }

    /**
     * @param string $channel
     * @param string $message
     */
    public function publish($channel, $message)
    {
        $this->connection->publish($channel, $message);
    }
}