<?php declare(strict_types=1);

namespace mii\log;

use mii\core\Component;

class Logger extends Component
{
    public array $targets = [];

    public array $targets_objs = [];

    public const DEBUG = 1;
    public const INFO = 2;
    public const NOTICE = 4;
    public const WARNING = 8;
    public const ERROR = 16;
    public const CRITICAL = 32;
    public const ALERT = 64;
    public const ALL = 127;

    public static array $level_names = [
        1 => 'debug',
        2 => 'info',
        4 => 'notice',
        8 => 'warn',
        16 => 'error',
        32 => 'crit',
        64 => 'alert'
    ];

    protected array $messages = [];

    protected int $flush_interval = 5;

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

        $this->messages = [];
    }


    protected function init_targets()
    {
        foreach ($this->targets as $name => $logger) {

            $ref = new \ReflectionClass($logger['class']);
            $this->targets_objs[$name] = $ref->newInstanceArgs([$logger]);
        }
    }


}