<?php

namespace mii\console;

use mii\util\Console;

class Controller
{

    public $name;

    public $description;

    public $color;

    public $interactive = true;

    public function __construct(Request $request, Response $response) {
        // Assign the request to the controller
        $this->request = $request;

        // Assign a response to the controller
        $this->response = $response;
    }


    protected function before() {
        return true;
    }


    protected function after()
    {

    }

    public function index($argv)
    {
        $this->_autogenerate_help();
    }

    protected function _autogenerate_help()
    {
        $class = new \ReflectionClass($this);

        $description = $class->getProperty('description')->getValue($this);
        if ($description)
            $this->stdout("\n  $description\n", Console::FG_GREEN);

        $public_methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        $methods = [];

        foreach ($public_methods as $m) {
            if ($m->class != static::class)
                continue;
            $methods[] = $m->name;
        }

        if (count($methods))
            $this->stdout("\n  Commands:");

        foreach ($methods as $method) {

            $this->stdout("\n\n    $method", Console::FG_YELLOW);

            $string = $class->getMethod($method)->getDocComment();
            $string = trim(str_replace(['/**', '*/'], '', $string));
            $array = explode("\n", $string);
            $comment = $array[0];
            if (strpos($comment, '*') === 0)
                $comment = trim(substr($comment, 1));

            if ($comment)
                $this->stdout("\t$comment");
        }

        $this->stdout("\n\n");
    }

    /**
     * Executes the given action and calls the [Controller::before] and [Controller::after] methods.
     *
     * Can also be used to catch exceptions from actions in a single place.
     *
     * 1. Before the controller action is called, the [Controller::before] method
     * will be called.
     * 2. Next the controller action will be called.
     * 3. After the controller action is called, the [Controller::after] method
     * will be called.
     *
     * @throws  HTTP_Exception_404
     * @return  Response
     */
    public function execute($params = []) {
        //$method = new \ReflectionMethod($this, $this->request->action());

        $this->before();

        call_user_func([$this, $this->request->action], $this->request->params);

        $this->after();

        return $this->response;

    }

    public function is_color_enabled($stream = \STDOUT) {
        return $this->color === null ? Console::stream_supports_ansi_colors($stream) : $this->color;
    }

    public function ansi_format($string) {
        if ($this->is_color_enabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansi_format($string, $args);
        }
        return $string;
    }

    public function stdout($string) {
        if ($this->is_color_enabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansi_format($string, $args);
        }
        return Console::stdout($string);
    }

    public function stderr($string) {
        if ($this->is_color_enabled(\STDERR)) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansi_format($string, $args);
        }
        return Console::stderr($string);
    }

    public function stdin() {
        return Console::stdin();
    }

    public function confirm($message, $default = false) {
        if ($this->interactive) {
            return Console::confirm($message, $default);
        } else {
            return true;
        }
    }

    protected function info($msg, $options = []) {
        $msg = strtr($msg, $options);
        $this->stdout($msg . "\n", Console::FG_GREEN);
        \Mii::info($msg, 'console');
    }

    protected function warning($msg, $options = []) {
        $msg = strtr($msg, $options);
        $this->stdout($msg . "\n", Console::FG_PURPLE);
        \Mii::warning(strtr($msg, $options), 'console');
    }

    protected function error($msg, $options = []) {
        $msg = strtr($msg, $options);
        $this->stderr($msg . "\n", Console::FG_RED);
        \Mii::error(strtr($msg, $options), 'console');
    }

}