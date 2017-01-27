<?php

namespace mii\log;

use Mii;
use mii\core\ErrorException;
use mii\core\Exception;
use mii\util\Debug;

class File extends Target {


    protected $base_path;
    protected $file = '';
    protected $levels = Logger::ALL;
    protected $category;

    protected $is_init = false;

    protected $messages = [];


    public function __construct($params) {
        $this->file = Mii::resolve($params['file']);
        $this->levels = isset($params['levels']) ? $params['levels'] : Logger::ALL;
        $this->category = isset($params['category']) ? $params['category'] : [] ;
    }


    public function log($level, $message, $category) {

        if(! ($this->levels & $level))
            return;

        if(count($category) AND $this->category AND !in_array($category, $this->category))
            return;


        $this->messages[] = [$message, $level, $category, time()];

    }


    public function flush() {

        if(!count($this->messages))
            return;

        $path = dirname($this->file);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $text = implode("\n", array_map([$this, 'format_message'], $this->messages)) . "\n";
        $this->messages = [];

        if (($fp = @fopen($this->file, 'a')) === false) {

            throw new ErrorException;exit;
            // TODO: throw new Exception("Unable to append to log file: {$this->file}");
        }
        @flock($fp, LOCK_EX);

        @fwrite($fp, $text);
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }


    public function format_message($message)
    {
        list($text, $level, $category, $timestamp) = $message;

        $level = Logger::$level_names[$level];
        if (!is_string($text)) {

            if ($text instanceof \Exception || $text instanceof \Throwable || $text instanceof Exception) {
                $text = (string) $text;
            } else {
               $text = var_export($text);
            }
        }
        //$prefix = $this->getMessagePrefix($message);
        return date('Y-m-d H:i:s', $timestamp) . " [$level][".$category."] $text";

    }
}