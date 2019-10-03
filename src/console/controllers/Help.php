<?php

namespace mii\console\controllers;

use mii\console\Controller;
use mii\util\Console;

class Help extends Controller
{

    public function index($argv) {

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

        $this->stdout("\n");

        foreach ($list as $controller) {

            if ($controller['class'] == static::class)
                continue;

            $class = new $controller['class']($this->request, $this->response);

            $desc = ($class->description) ? " " . $class->description . " " : '';
            $this->stdout($controller['command'], Console::FG_GREEN);
            $padding = max(1, 12-\strlen($controller['command']));
            $this->stdout(str_pad(" ", $padding, " ").$desc . "\n\n", Console::FG_GREY);

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
