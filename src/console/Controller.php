<?php

namespace mii\console;

use mii\util\Console;

class Controller
{

    public $name;

    public $description;

    public $color;

    public $interactive = true;

    public $auto_params = false;

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


        $max = 0;
        foreach($methods as $method) {
            $max = max(strlen($method), $max);
        }

        $max = min($max, 30); // limit our max length to 30

        foreach ($methods as $method) {

            $this->stdout("\n\n    $method", Console::FG_YELLOW);

            $string = $class->getMethod($method)->getDocComment();
            $string = trim(str_replace(['/**', '*/'], '', $string));
            $array = explode("\n", $string);
            $comment = $array[0];
            if (strpos($comment, '*') === 0)
                $comment = trim(substr($comment, 1));

            if ($comment) {
                $this->stdout(str_pad(" ", $max-strlen($method), " ")."\t$comment");
            }
        }

        $this->stdout("\n\n");
    }

    protected function execute_action($action, $params)
    {
        $method = new \ReflectionMethod($this, $action);

        if (!$method->isPublic())
            throw new CliException("Cannot access not public method");

        $args = [];
        $missing = [];
        $action_params = [];
        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $params)) {
                if ($param->isArray()) {
                    $args[] = $action_params[$name] = is_array($params[$name]) ? $params[$name] : [$params[$name]];
                } elseif (!is_array($params[$name])) {
                    $args[] = $action_params[$name] = $params[$name];
                } else {
                    throw new CliException('Invalid data received for parameter ":param".', [
                        ':param' => $name,
                    ]);
                }
                unset($params[$name]);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $action_params[$name] = $param->getDefaultValue();
            } else {
                $missing[] = $name;
            }
        }
        if (!empty($missing)) {
            if($missing[0] === 'argv' AND $action === 'index') {
                $args = [$params]; // Emulate old behavior for backwards compatibility
            } else {
                throw new CliException( 'Missing required parameters: ":params"', [
                    ':params' => implode(', ', $missing),
                ]);
            }
        }
        $this->action_params = $action_params;

        return call_user_func_array([$this, $action], $args);
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
     * @throws  CliException
     * @return  Response
     */
    public function execute()
    {
        //$method = new \ReflectionMethod($this, $this->request->action());

        $this->before();

        if($this->auto_params)
            $this->execute_action($this->request->action, $this->request->params);
        else
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