<?php

namespace mii\log;

use mii\core\Component;

class Logger extends Component {

    public $targets = [];

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
    public static $level_names = [
        1 => 'DEBUG',
        2 => 'INFO',
        4 => 'NOTICE',
        8 => 'WARNING',
        16 => 'ERROR',
        32 => 'CRITICAL',
        64 => 'ALERT'
    ];



    public function init(array $config = []) : void {

        parent::init($config);

        foreach($this->targets as $name => $logger) {

            $ref = new \ReflectionClass($logger['class']);
            $this->targets[$name] = $ref->newInstanceArgs([$logger]);
        }

        register_shutdown_function(function () {
            $this->flush();
        });
    }

    public function log($level, $message, $category) {
        foreach($this->targets as $target) {
            $target->log($level, $message, $category);
        }
    }

    public function flush() {

        foreach($this->targets as $target) {
            $target->flush();
        }
    }


}