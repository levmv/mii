<?php

namespace mii\console\controllers;

use mii\console\Controller;
use mii\util\Console;

class Help extends Controller
{

    public function index($argv) {

        $paths = array_replace([
            'app\\console' => '@app/console',
            'mii\\console\\controllers' => __DIR__
        ], config('console.ns_paths', []));


        $namespaces = config('console.namespaces', [
            'app\\console',
            'mii\\console\\controllers'
        ]);

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
            $this->stdout($desc . "\n\n", Console::FG_GREY);

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