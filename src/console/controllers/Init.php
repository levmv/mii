<?php

namespace mii\console\controllers;

use mii\console\Controller;

class Init extends Controller {

    public $description = 'Initialize application environments';

    protected $environments;

    protected $config;

    public function before() {

        $this->config = [
            'development' => [
                'path' => path('root').'/environments/development',

                'rights' => [
                    'public/assets' => 0775,
                    'public/files' => 0775,
                    'tmp' => 0775,
                    'app/logs' => 0775,
                    'mii' => 0755
                ]
            ],
            'production' => [
                'path' => path('root').'/environments/production',

                'rights' => [
                    'public/assets' => 0775,
                    'public/files' => 0775,
                    'tmp' => 0775,
                    'app/logs' => 0775,
                    'mii' => 0755
                ]
            ]
        ];

        $envs = config('environments');

        if(count($envs)) {
            $this->config = $envs;
        }

        return parent::before();
    }


    public function index($argv) {

        $this->stdout("Which environment do you want the application to be initialized in?\n\n");

        $i = 1;
        $envs = [];
        foreach($this->config as $name => $value) {
            $this->stdout($i.'. '.$name."\n");
            $envs[$i] = $name;
            $i++;
        }

        $in = $this->stdin();
        if($in === 'q')
            return;

        $in = intVal($in);

        if($in >= $i) {
            $this->error('There is no environment under number '.$in);
            return;
        }

        if(! $this->confirm("Are you sure you want to initialize the selected environment?")) {
            return;
        }

        $config = $this->config[$envs[$in]];

        $this->env_copy($config['path'], path('root'));


        if(isset($config['rights'])) {
            $this->set_rights($config['rights']);
        }


    }

    protected function set_rights($paths) {

        $base_path = path('root');
        foreach($paths as $path => $rights) {
            if(!is_dir($path)) {
                mkdir($path, $rights, true);
            }
            $this->stdout("chmod ".decoct($rights)." ".$path."\n");
            chmod($base_path.'/'.$path, $rights);
        }

    }


    protected function env_copy($from, $to) {

        // Check for symlinks
        if (is_link($from)) {
            return symlink(readlink($from), $to);
        }

        // Simple copy for a file
        if (is_file($from)) {
            $this->stdout("Copying ".$from."\n");
            return copy($from, $to);
        }

        // Make destination directory
        if (!is_dir($to)) {
            $this->stdout("mkdir ".$to."\n");
            mkdir($to);
        }

        $dir = dir($from);
        while (false !== $entry = $dir->read()) {

            if ($entry == '.' || $entry == '..' || $entry == '.git') {
                continue;
            }

            // Deep copy directories
            $this->env_copy($from."/".$entry, $to."/".$entry);
        }

        // Clean up
        $dir->close();
    }


}