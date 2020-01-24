<?php

namespace mii\console;


use mii\core\Component;
use mii\core\Exception;
use mii\util\Console;

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

        if(empty($argv))
            return;

        $this->controller = ucfirst($argv[0]);
        array_shift($argv);

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

        if(empty($this->controller)) {
            $this->gen_help();
            return 0;
        }

        $namespaces = config('console.namespaces', [
            'app\\console'
        ]);

        if (!\count($namespaces)) {
            throw new Exception("console.namespaces is empty");
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
            throw new Exception("Unknown command {$this->controller}");

        // Create a new instance of the controller
        $controller = new $controller_class;
        $controller->request = $this;

        return (int) $controller->execute();
    }


    public function param($name, $default = null) {
        return $this->params[$name] ?? $default;
    }



    public function gen_help() {

        $namespaces = config('console.namespaces', [
            'app\\console',
            'mii\\console\\controllers'
        ]);

        $paths = array_replace([
            'app\\console' => '@app/console',
            'mii\\console\\controllers' => __DIR__
        ], config('console.ns_paths', $this->get_paths_from_composer($namespaces)));


        $list = [];
        foreach ($namespaces as $namespace) {

            if(!isset($paths[$namespace])) {

                $this->error("Dont know path for $namespace. Skip");
                continue;
            }

            $this->find_controllers($namespace, \Mii::resolve($paths[$namespace]), $list);
        }

        Console::stdout("\n");

        foreach ($list as $controller) {

            if ($controller['class'] == static::class)
                continue;

            $class = new $controller['class']();

            $desc = ($class->description) ? " " . $class->description . " " : '';
            Console::stdout(Console::ansi_format($controller['command'], [Console::FG_GREEN]));
            $padding = max(1, 12-\strlen($controller['command']));
            Console::stdout(Console::ansi_format(str_pad(" ", $padding, " ").$desc . "\n\n", [Console::FG_GREY]));
        }
    }


    private function get_paths_from_composer($namespaces) {

        try {
            $loader = new \Composer\Autoload\ClassLoader();

            $map = require path('vendor') . '/composer/autoload_namespaces.php';
            foreach ($map as $namespace => $path) {
                $loader->set($namespace, $path);
            }

            $map = require path('vendor') . '/composer/autoload_psr4.php';

            foreach ($map as $namespace => $path) {
                $loader->setPsr4($namespace, $path);
            }

            $classMap = require path('vendor') . '/composer/autoload_classmap.php';
            if ($classMap) {
                $loader->addClassMap($classMap);
            }

            $data = $loader->getPrefixesPsr4();
            $paths = [];


            foreach($namespaces as $ns) {

                foreach($data as $prefix => $path) {
                    if(strpos($ns, $prefix) === 0) {

                        $path[0] .= str_replace('\\', '/', substr($ns, \strlen($prefix)-1));
                        $paths[$ns] = $path[0];
                    }
                }
            }

            return $paths;
        } catch (\Throwable $t) {
            return [];
        }
    }

    protected function find_controllers($namespace, $path, &$files) {

        $dir = dir($path);
        while (false !== $entry = $dir->read()) {
            if ($entry == '.' || $entry == '..' || $entry == '.git' || is_dir($dir->path . "/" . $entry)) {
                continue;
            }

            $info = pathinfo($path . '/' . $entry);

            if (!isset($info['extension']) || $info['extension'] !== 'php') {
                continue;
            }

            if (!isset($files[$info['filename']]))
                $files[$info['filename']] = [
                    'class' => $namespace . '\\' . $info['filename'],
                    'command' => mb_strtolower($info['filename'], 'utf-8')
                ];
        }

        // Clean up
        $dir->close();
    }

}