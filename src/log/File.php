<?php

namespace mii\log;

use Mii;
use mii\core\ErrorException;
use mii\util\FS;

class File extends Target
{
    protected $file = '';

    public function init(array $config = []): void
    {
        parent::init($config);

    }


    public function process(array $messages)
    {
        $this->file = Mii::resolve($this->file);
        FS::mkdir(\dirname($this->file));

        $text = implode("\n", array_map([$this, 'format_message'], $messages)) . "\n";

        if (($fp = fopen($this->file, 'a')) === false) {
            error_log("Unable to append to log file: {$this->file}");
            return;
        }
        flock($fp, LOCK_EX);
        fwrite($fp, $text);
        flock($fp, LOCK_UN);
        fclose($fp);
    }


}