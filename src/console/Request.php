<?php

namespace mii\console;


class Request extends \mii\core\Request {
    /**
     * @var  array   parameters from the route
     */
    public $_params = [];


    public function init($uri = null)
    {

        if (isset($_SERVER['argv'])) {
            $argv = $_SERVER['argv'];
            array_shift($argv);
        } else {
            $argv = [];
        }


        if (isset($argv[0])) {
            $controller = $argv[0];
            array_shift($argv);
        } else {
            $controller = '';
        }

        $this->controller($controller);
        $this->action('index');

        $params = [];
        $c = 0;
        foreach ($argv as $param) {
            if (preg_match('/^--(\w+)(=(.*))?$/', $param, $matches)) {
                $name = $matches[1];
                $params[$name] = isset($matches[3]) ? $matches[3] : true;

            } else {
                if($c == 0) {
                    $this->action($param);
                } else {
                    $params[] = $param;
                }
            }
            $c++;
        }

        $this->_params = $params;

    }

    function execute() {
        // Controller

        $controller = $this->controller();

        $config = config('console');

        if(isset($config['namespace'])) {
            $controller = $config['namespace'].'\\'.$controller;
        }

        try {

            if (!class_exists($controller)) {

                throw new CliException('Unknown command :com',
                    [':com' => $controller]
                );
            }
            // Load the controller using reflection
            $class = new \ReflectionClass($controller);

            if ($class->isAbstract()) {
                throw new CliException(
                    'Cannot create instances of abstract :controller',
                    [':controller' => $controller]
                );
            }
            $response = new Response;

            // Create a new instance of the controller
            $controller = $class->newInstance($this, $response);

            // Run the controller's execute() method
            $response = $class->getMethod('execute')->invoke($controller, $this->_params);


            if (!$response instanceof Response) {
                // Controller failed to return a Response.
                throw new CliException('Controller failed to return a Response');
            }
        } catch (Exception $e) {
            // Generate an appropriate Response object
            $response = CliException::handler($e);
        }


        return $response;

    }


}