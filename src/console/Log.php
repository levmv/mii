<?php

namespace mii\console;

use mii\log\File;
use mii\log\Logger;
use mii\util\Console;

class Log extends File
{

    protected $controllers;

    public function process(array $messages)
    {
        foreach ($messages as $message) {
            $this->print_error($message[1], $message[0]);
        }
    }

    private function print_error($level, $message)
    {
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