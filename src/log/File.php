<?php declare(strict_types=1);

namespace mii\log;

use Mii;
use mii\util\FS;

class File extends Target
{
    protected $file = '';

    public function init(array $config = []): void
    {
        parent::init($config);
    }


    public function process(array $messages): void
    {
        $this->file = Mii::resolve($this->file);
        $log_dir = \dirname($this->file);
        if (!\is_dir($log_dir)) {
            FS::mkdir(\dirname($this->file));
        }

        $text = \implode("\n", \array_map([$this, 'formatMessage'], $messages)) . "\n";

        if (($fp = \fopen($this->file, 'a')) === false) {
            \error_log("Unable to append to log file: {$this->file}");
            return;
        }
        \flock($fp, \LOCK_EX);
        \fwrite($fp, $text);
        \flock($fp, \LOCK_UN);
        \fclose($fp);
    }
}
