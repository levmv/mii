<?php

namespace mii\log;


use mii\core\Component;
use mii\web\App;

abstract class Target extends Component
{
    protected $levels = Logger::ALL;

    protected $categories;

    protected $except;

    protected $with_trace = true;

    protected $with_context = true;

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

        $trace = '';
        $context = '';

        if (!is_string($text)) {

            if ($text instanceof \Throwable) {

                if($this->with_trace) {
                    $trace = "\n".\mii\util\Debug::short_text_trace($text->getTrace());
                }

                $text = (string) $text;

            } else {
                $text = var_export($text);
            }
        }

        if($this->with_context && \Mii::$app instanceof App) {
            $context = sprintf("\n%s%s: %s",
                \Mii::$app->request->method(),
                \Mii::$app->request->is_ajax() ? '[Ajax]' : '',
                $_SERVER['REQUEST_URI']
            );
        }

        return date('Y-m-d H:i:s', $timestamp) . " [$level][" . $category . "] $text$context$trace";
    }

}