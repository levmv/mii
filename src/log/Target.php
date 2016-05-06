<?php

namespace mii\log;


abstract class Target {


    protected $messages = [];


    /**
     * Adds a log record at an arbitrary level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  mixed   $level   The log level
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
    abstract public function log($level, $message, array $context = []);


    public function flush() {

    }
}