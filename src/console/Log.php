<?php

namespace mii\console;

use mii\log\File;
use mii\log\Logger;
use mii\util\Console;

class Log extends File
{

    protected $controllers;

    public function log($level, $message, $category) {

        if (!($this->levels & $level))
            return;

        if ($this->category AND !in_array($category, $this->category))
            return;

        $this->messages[] = [$message, $level, $category, time()];

        if (!is_string($message)) {

            if ($message instanceof \Exception) {
                $message = (string)$message;
            }
        }

        $params = [];

        switch ($level) {
            case Logger::WARNING:
                $params = [Console::FG_PURPLE];
                break;
            case Logger::ERROR:
                $params = [Console::FG_RED];
                break;
            case Logger::INFO:
                $params = [Console::FG_GREEN];
                break;
        }

        if (count($params))
            $message = Console::ansi_format($message, $params);

        Console::stdout($message . "\n");

    }


}