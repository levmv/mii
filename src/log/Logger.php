<?php

namespace mii\log;

use mii\core\Component;

class Logger extends Component
{
    public $targets = [];

    public $targets_objs = [];

    const DEBUG = 1;
    const INFO = 2;
    const NOTICE = 4;
    const WARNING = 8;
    const ERROR = 16;
    const CRITICAL = 32;
    const ALERT = 64;
    const ALL = 127;

    public static $level_names = [
        1 => 'DEBUG',
        2 => 'INF',
        4 => 'NOTICE',
        8 => 'WARNING',
        16 => 'ERR',
        32 => 'CRITICAL',
        64 => 'ALERT'
    ];

    protected $messages = [];

    protected $flush_interval = 1;

    public function init(array $config = []): void
    {
        parent::init($config);

        register_shutdown_function(function () {
            $this->flush();
        });
    }

    public function log($level, $message, $category)
    {
        $this->messages[] = [$message, $level, $category, time()];

        if ($this->flush_interval > 0 && $this->flush_interval > \count($this->messages))
            $this->flush();
    }

    public function flush()
    {
        if (empty($this->messages))
            return;

        if (!empty($this->targets) && empty($this->targets_objs))
            $this->init_targets();

        foreach ($this->targets_objs as $target) {
            $target->collect($this->messages);
        }
    }


    protected function init_targets()
    {
        foreach ($this->targets as $name => $logger) {

            $ref = new \ReflectionClass($logger['class']);
            $this->targets_objs[$name] = $ref->newInstanceArgs([$logger]);
        }
    }


}