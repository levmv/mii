<?php declare(strict_types=1);

namespace mii\log;

use mii\core\Component;

class Logger extends Component
{
    public array $targets = [];

    public array $targets_objs = [];

    final public const DEBUG = 1;
    final public const INFO = 2;
    final public const NOTICE = 4;
    final public const WARNING = 8;
    final public const ERROR = 16;
    final public const CRITICAL = 32;
    final public const ALERT = 64;
    final public const ALL = 127;

    public static array $level_names = [
        1 => 'debug',
        2 => 'inf',
        4 => 'notice',
        8 => 'warn',
        16 => 'err',
        32 => 'crit',
        64 => 'alert',
    ];

    protected array $messages = [];

    protected int $flush_interval = 5;

    public function init(array $config = []): void
    {
        parent::init($config);

        \register_shutdown_function(function () {
            $this->flush();
        });
    }

    public function log($level, $message, $category)
    {
        $this->messages[] = [$message, $level, $category, \time()];

        if ($this->flush_interval > 0 && \count($this->messages) > $this->flush_interval) {
            $this->flush();
        }
    }

    public function flush()
    {
        if (empty($this->messages)) {
            return;
        }

        if (!empty($this->targets) && empty($this->targets_objs)) {
            $this->initTargets();
        }

        foreach ($this->targets_objs as $target) {
            $target->collect($this->messages);
        }

        $this->messages = [];
    }


    protected function initTargets()
    {
        foreach ($this->targets as $name => $logger) {
            $ref = new \ReflectionClass($logger['class']);
            $this->targets_objs[$name] = $ref->newInstanceArgs([$logger]);
        }
    }
}
