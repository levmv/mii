<?php

namespace mii\console\controllers;


use mii\console\Controller;
use mii\db\DB;

class Migrate extends Controller {

    public $description = 'DB migrations';

    protected $migrate_table = 'migrations';

    protected $migrations_list = [];

    protected $applied_migrations;

    protected $migrations_paths;

    public function before() {

        $config = config('migrate', []);

        foreach($config as $name => $value)
            $this->$name = $value;

        if($this->migrations_paths === null)
            $this->migrations_paths = [path('app').'/migrations'];


        try {
            $this->applied_migrations = DB::select('SELECT `name`, `date` FROM `'.$this->migrate_table.'`')->index_by('name')->all();

        } catch (\Exception $e) {
            $this->info('Trying to create table :table', [':table' => $this->migrate_table]);

            DB::update('CREATE TABLE `'.$this->migrate_table.'` (
              `name` varchar(180) NOT NULL,
              `date` int(11),
               PRIMARY KEY (`name`)
            );');

            $this->applied_migrations = [];
        }


        $files = [];

        for($i=0;$i<count($this->migrations_paths);$i++)
            $this->migrations_paths[$i] = \Mii::resolve($this->migrations_paths[$i]);

        foreach($this->migrations_paths as $migrations_path) {

            if(! is_dir($migrations_path)) {
                $this->warning('Directory :dir does not exist', [':dir' => $migrations_path]);
                mkdir($migrations_path, 0775);
            }

            $scan = scandir($migrations_path);
            foreach($scan as $file) {
                if ($file[0] == '.')
                    continue;

                $info = pathinfo($file);

                if($info['extension'] !== 'php')
                    continue;

                $name = $info['filename'];

                $this->migrations_list[$name] = [
                    'name' => $name,
                    'file' => $migrations_path.'/'.$file,
                    'applied' => isset($this->applied_migrations[$name]),
                    'date' => 0
                ];
            }

        }

        uksort($this->migrations_list, 'strnatcmp');


    }


    public function create($argv) {

        $custom_name = false;

        if(count($argv)) {

            $custom_name = mb_strtolower($argv[0], 'utf-8');
        }

        DB::begin();
        try {
         //   $migration_id = DB::insert('INSERT INTO `'.$this->migrate_table.'`(`apply_time`)  VALUES (0)')[0];

            $name = 'm'.gmdate('ymd_His');
            if($custom_name)
                $name = $name.'_'.$custom_name;

            $file = '<?php
// '.strftime('%F %T').'

use mii\db\Migration;
use mii\db\DB;

class '.$name.' extends Migration {

    public function up() {

    }

    public function down() {
        return false;
    }

}
';
            reset($this->migrations_paths);
            file_put_contents(current($this->migrations_paths).'/'.$name.'.php', $file);

            DB::commit();

            $this->info('migration :name created', [':name' => $name]);

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

    }


    public function index($argv) {

        $limit = count($argv) ? $argv[0] : null;

        $this->_up($limit);
    }


    private function _up($limit = null) {


        $applied = 0;

        $migrations = $this->migrations_list;

        $total = count($migrations);
        $limit = (int) $limit;

        if ($limit > 0) {
            $migrations = array_slice($migrations, 0, $limit);
        }

        foreach($migrations as $migration) {

            if($migration['applied'])
                continue;

            $name = $migration['name'];

            $this->info('Loading migration #:name', [':name' => $name]);

            $obj = $this->load_migration($name);
            $obj->init();
            if($obj->up() === false) {
                $this->error('Migration #:name failed. Stop.', [':name' => $name]);
                return;
            };

            DB::insert('INSERT INTO`'.$this->migrate_table.'`(`name`, `date`) VALUES(:name, :date)',
                [
                    ':name' => $name,
                    ':date' => time()
                ]);

            $this->info('Migration up successfully', [':name' => $name]);
            $applied++;

        }
        if(!$applied) {
            $this->warning('No new migration found');
        }
    }


    public function load_migration($class) {

        require_once(current($this->migrations_paths).'/'.$class.'.php');

        return new $class;
    }

}