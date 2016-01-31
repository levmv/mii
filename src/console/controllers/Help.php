<?php

namespace mii\console\controllers;

use mii\console\Controller;

class Help extends Controller {

    public function index($argv) {

        $dirs = [
            'app' => [
                'path' => path('app').'/console',
                'namespace' => config('console.namespace', 'app\\controllers')
            ],
            'mii' => [
                'path' => __DIR__,
                'namespace' => 'mii\\console\\controllers'
            ]
        ];

        $list = [];
        foreach($dirs as $name => $dir) {
            $list[] = $this->find_controllers($dir['namespace'], $dir['path']);
        }

        $help = [];

        foreach($list as $controllers) {
            foreach($controllers as $controller) {

                if($controller['class'] == __CLASS__)
                    continue;

                $class = new $controller['class']($this->request, $this->response);

                $desc = ($class->description) ? " [".$class->description."]" : '';
                $this->stdout($controller['command'].$desc."\n\n");

            }
        }
    }

    protected function find_controllers($namespace, $path) {
        $files = [];

        $dir = dir($path);
        while (false !== $entry = $dir->read()) {

            if ($entry == '.' || $entry == '..' || $entry == '.git') {
                continue;
            }

            $info = pathinfo($path.'/'.$entry);

            if($info['extension'] !== 'php') {
                continue;
            }

            $files[] = [
                'class' => $namespace.'\\'.$info['filename'],
                'command' => mb_strtolower($info['filename'], 'utf-8')
            ];
        }

        // Clean up
        $dir->close();

        return $files;
    }

}