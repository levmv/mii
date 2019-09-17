<?php

namespace mii\log;


use mii\core\Component;

abstract class Target extends Component
{
    protected $levels = Logger::ALL;

    protected $categories;

    protected $except;

    protected $messages = [];

    protected function filter(array $messages)
    {
        foreach ($messages as $i => $message) {

            if (!($this->levels & $message[1]))
                continue;

            $pass = empty($this->categories);

            if (!empty($this->categories)) {
                foreach ($this->categories as $category) {
                    if ($message[2] === $category ||
                        substr_compare($category, '*', -1, 1) === 0 && strpos($message[2], rtrim($category, '*')) === 0) {

                        $pass = true;
                        break;
                    }
                }
            }

            if ($pass && !empty($this->except)) {
                foreach ($this->except as $category) {
                    $prefix = rtrim($category, '*');
                    if (($message[2] === $category || $prefix !== $category) && strpos($message[2], $prefix) === 0) {
                        $pass = false;
                        break;
                    }
                }

            }

            if (!$pass) {
                unset($messages[$i]);
            }
        }

        return $messages;
    }

    public function collect(array $messages)
    {
        $messages = $this->filter($messages);

        if (!empty($messages))
            $this->process($messages);
    }

    abstract public function process(array $messages);

    public function format_message($message)
    {
        list($text, $level, $category, $timestamp) = $message;

        $level = Logger::$level_names[$level];
        if (!is_string($text)) {

            if ($text instanceof \Throwable) {
                $text = (string)$text;
            } else {
                $text = var_export($text);
            }
        }
        return date('Y-m-d H:i:s', $timestamp) . " [$level][" . $category . "] $text";
    }

}