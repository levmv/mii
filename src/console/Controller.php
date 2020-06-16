<?php declare(strict_types=1);

namespace mii\console;

use mii\core\Exception;
use mii\util\Console;

class Controller
{
    public $color;

    public $interactive = true;

    public $auto_params = true;

    public $request;

    public $response_code = 0;

    public function __construct()
    {
    }

    protected function before()
    {
        return true;
    }

    protected function after()
    {

    }

    public function index($argv)
    {
        $this->_autogenHelp();
    }

    protected function _autogenHelp()
    {
        $ref = new \ReflectionClass($this);

        $doc = $ref->getStaticPropertyValue('description', '');
        $full = '';

        if (!$doc) {
            [$doc, $full] = Request::getPhpdocSummary($ref);
        }

        if ($doc) {
            Console::stdout("\n$doc\n$full\n", Console::FG_GREEN);
        }

        $methods = Request::getControllerActions($ref);

        if (!\count($methods)) {
            return;
        }

        $controller = strtolower($this->request->controller);

        foreach ($methods as ['name' => $method,
                 'summary' => $summary,
                 'desc' => $desc,
                 'args' => $args]
        ) {
            $args = implode(' ', $args);
            Console::stdout("\n$controller $method $args", Console::FG_YELLOW);

            $this->stdout("\n$summary\n$desc", Console::FG_GREY);
        }

        $this->stdout("\n\n");
    }

    protected function execute_action($action, $params)
    {
        $method = new \ReflectionMethod($this, $action);

        if (!$method->isPublic()) {
            throw new Exception("Cannot access not public method");
        }

        $args = [];
        $missing = [];
        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (\array_key_exists($name, $params)) {
                if ($param->isArray()) {
                    $args[] = \is_array($params[$name]) ? $params[$name] : [$params[$name]];
                } elseif (!\is_array($params[$name])) {
                    $args[] = $params[$name];
                } else {
                    throw new Exception("Invalid data received for parameter \"$name\".");
                }
                unset($params[$name]);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $missing[] = $name;
            }
        }
        if (!empty($missing)) {
            if ($missing[0] === 'argv' && $action === 'index') {
                $args = [$params]; // Emulate old behavior for backwards compatibility
            } else {
                throw new Exception('Missing required parameters: "' . implode(', ', $missing) . '"');
            }
        }

        return \call_user_func_array([$this, $action], $args);
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
     * @return  int
     * @throws  Exception
     */
    public function _execute()
    {

        $this->before();

        if ($this->auto_params) {
            $this->response_code = (int)$this->execute_action($this->request->action, $this->request->params);
        } else {
            $this->response_code = (int)\call_user_func([$this, $this->request->action], $this->request->params);
        }

        $this->after();

        return $this->response_code;
    }


    protected function stdout($string)
    {
        return Console::stdout($string);
    }

    protected function stderr($string)
    {
        return Console::stderr($string);
    }

    protected function stdin()
    {
        return Console::stdin();
    }

    protected function confirm($message, $default = false)
    {
        if ($this->interactive) {
            return Console::confirm($message, $default);
        }

        return true;
    }

    protected function info($msg, $options = [])
    {
        $msg = strtr($msg, $options);
        Console::stdout($msg . "\n", Console::FG_GREEN);
        \Mii::info($msg, 'console');
    }

    protected function warning($msg, $options = [])
    {
        $msg = strtr($msg, $options);
        Console::stdout($msg . "\n", Console::FG_PURPLE);
        \Mii::warning(strtr($msg, $options), 'console');
    }

    protected function error($msg, $options = [])
    {
        $msg = strtr($msg, $options);
        Console::stderr($msg . "\n", Console::FG_RED);
        \Mii::error(strtr($msg, $options), 'console');
    }

}
