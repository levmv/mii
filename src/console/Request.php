<?php

namespace mii\console;


use mii\core\Component;

class Request extends Component
{
    /**
     * @var  array   parameters from the route
     */
    public $params = [];


    /**
     * @var  string  controller to be executed
     */
    public $controller;

    /**
     * @var  string  action to be executed in the controller
     */
    public $action;


    public function init(array $config = []): void {
        foreach ($config as $key => $value)
            $this->$key = $value;

        if (isset($_SERVER['argv'])) {
            $argv = $_SERVER['argv'];
            array_shift($argv);
        } else {
            $argv = [];
        }

        if (isset($argv[0])) {
            $controller = ucfirst($argv[0]);
            array_shift($argv);
        } else {
            $controller = 'Help';
        }

        $this->controller = $controller;
        $this->action = 'index';

        $params = [];
        $c = 0;
        foreach ($argv as $param) {
            if (preg_match('/^--(\w+)(=(.*))?$/', $param, $matches)) {
                $name = $matches[1];
                $value = isset($matches[3]) ? $matches[3] : true;

                if(isset($params[$name])) {
                    $params[$name] = (array) $params[$name];
                    $params[$name][] = $value;
                } else {
                    $params[$name] = $value;
                }
            } else {
                if ($c === 0) {
                    $this->action = $param;
                } else {
                    $params[] = $param;
                }
            }
            $c++;
        }

        $this->params = $params;

    }

    function execute() {
        // Controller

        $controller_class = $controller = $this->controller;

        $namespaces = config('console.namespaces', []);
        if (\count($namespaces)) {
            $controller_class = array_shift($namespaces) . '\\' . $controller;
        }

        while (!class_exists($controller_class)) {

            // Try next controller

            if (\count($namespaces)) {
                $controller_class = array_shift($namespaces) . '\\' . $controller;
                continue;
            } else {

                // try mii controller
                $controller_class = 'mii\\console\\controllers\\' . $controller;

                if (!class_exists($controller_class)) {
                    throw new CliException("Unknown command $controller");
                }
            }
        }
        // Load the controller using reflection
        $class = new \ReflectionClass($controller_class);

        if ($class->isAbstract()) {
            throw new CliException("Cannot create instances of abstract $controller");
        }
        $response = new Response;

        // Create a new instance of the controller
        $controller = $class->newInstance($this, $response);

        // Run the controller's execute() method
        $response = $class->getMethod('execute')->invoke($controller, $this->params);


        if (!$response instanceof Response) {
            // Controller failed to return a Response.
            throw new CliException('Controller failed to return a Response');
        }


        return $response;

    }


    public function param($name, $default = null) {
        return $this->params[$name] ?? $default;
    }


}