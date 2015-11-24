<?php

namespace mii\console\controllers;


use mii\db\DB;

class Init extends Base {


    protected $environments;

    protected $config;

    public function before() {

        $this->config = [
            'development' => [
                'path' => \Mii::path('app').'/environments/development/',

                'rights' => [
                    'public/assets' => 0775,
                    'public/files' => 0775,
                    'tmp' => 0775,
                    'app/logs' => 0775,
                    'mii' => 0755
                ]
            ]
        ];

        return parent::before();
    }


    public function index($argv) {




    }

    protected function copy($from, $to) {

        // Check for symlinks
        if (is_link($from)) {
            return symlink(readlink($from), $to);
        }

        // Simple copy for a file
        if (is_file($from)) {
            return copy($from, $to);
        }

        // Make destination directory
        if (!is_dir($to)) {
            mkdir($to);
        }

        $dir = dir($from);
        while (false !== $entry = $dir->read()) {

            if ($entry == '.' || $entry == '..' || $entry == '.git') {
                continue;
            }

            // Deep copy directories
            $this->copy($from."/".$entry, $to."/".$entry);
        }

        // Clean up
        $dir->close();
    }


}