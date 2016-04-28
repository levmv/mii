<?php

namespace mii\console\controllers;

use mii\console\Controller;
use mii\util\Console;

class Help extends Controller {

    public function index($argv) {

        $dirs = config('console.dirs', [
            'app' => [
                'path' => path('app').'/console',
                'namespace' => config('console.namespace', 'app\\console')
            ],
            'mii' => [
                'path' => __DIR__,
                'namespace' => 'mii\\console\\controllers'
            ]
        ]);

        $list = [];
        foreach($dirs as $name => $dir) {
            $this->find_controllers($dir['namespace'], $dir['path'], $list);
        }

        $help = [];

        $this->stdout("\n");
        foreach($list as $controller) {

            if($controller['class'] == static::class)
                continue;

            $class = new $controller['class']($this->request, $this->response);

            $desc = ($class->description) ? " [".$class->description."]" : '';
            $this->stdout($controller['command'], Console::FG_GREEN);
            $this->stdout($desc."\n\n", Console::FG_GREY);

        }
    }

    protected function find_controllers($namespace, $path, &$files) {

        $dir = dir($path);
        while (false !== $entry = $dir->read()) {

            if ($entry == '.' || $entry == '..' || $entry == '.git') {
                continue;
            }

            $info = pathinfo($path.'/'.$entry);

            if($info['extension'] !== 'php') {
                continue;
            }

            if(!isset($files[$info['filename']]))
                $files[$info['filename']] = [
                    'class' => $namespace.'\\'.$info['filename'],
                    'command' => mb_strtolower($info['filename'], 'utf-8')
                ];
        }

        // Clean up
        $dir->close();
    }

}