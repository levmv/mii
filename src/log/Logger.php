<?php

namespace mii\log;


class Logger {

    /**
     * Detailed debug information
     */
    const DEBUG = 1;
    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     */
    const INFO = 2;
    /**
     * Uncommon events
     */
    const NOTICE = 4;
    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    const WARNING = 8;
    /**
     * Runtime errors
     */
    const ERROR = 16;
    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     */
    const CRITICAL = 32;
    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     */
    const ALERT = 64;


    const ALL = 127;

    /**
     * Logging levels from syslog protocol defined in RFC 5424
     *
     * @var array $levels Logging levels
     */
    protected static $level_names = [
        1 => 'DEBUG',
        2 => 'INFO',
        4 => 'NOTICE',
        8 => 'WARNING',
        16 => 'ERROR',
        32 => 'CRITICAL',
        64 => 'ALERT'
    ];


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
    //abstract public function log($level, $message, array $context = array());


}