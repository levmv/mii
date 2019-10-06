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

        $namespaces = config('console.namespaces', [
            'app\\console'
        ]);

        if (!\count($namespaces)) {
            throw new CliException("console.namespaces is empty");
        }

        // Failback namespace
        $namespaces[] = 'mii\\console\\controllers';

        while (count($namespaces)) {

            $controller_class = array_shift($namespaces) . '\\' . $this->controller;

            class_exists($controller_class); // always return false, but autoload class if it exist

            // real check
            if(class_exists($controller_class, false))
                break;

            $controller_class = false;
        }

        if(!$controller_class)
            throw new CliException("Unknown command {$this->controller}");

        // Create a new instance of the controller
        $controller = new $controller_class;
        $controller->request = $this;

        return (int) $controller->execute();
    }


    public function param($name, $default = null) {
        return $this->params[$name] ?? $default;
    }


}