<?php

namespace mii\console\controllers;


use mii\console\Controller;
use mii\db\DB;

/**
 * DB migration tool
 * @package mii\console\controllers
 */
class Migrate extends Controller
{
    protected $migrate_table = 'migrations';

    protected $migrations_list = [];

    protected $applied_migrations;

    protected $migrations_paths = [];

    protected function before()
    {

        $config = config('migrate', []);

        foreach ($config as $name => $value)
            $this->$name = $value;

        if (empty($this->migrations_paths))
            $this->migrations_paths = [path('app') . '/migrations'];


        try {
            $this->applied_migrations = DB::select('SELECT `name`, `date` FROM `' . $this->migrate_table . '`')->indexBy('name')->all();

        } catch (\Exception $e) {
            $this->info('Trying to create table :table', [':table' => $this->migrate_table]);

            DB::update('CREATE TABLE `' . $this->migrate_table . '` (
              `name` varchar(180) NOT NULL,
              `date` int(11),
               PRIMARY KEY (`name`)
            );');

            $this->applied_migrations = [];
        }

        for ($i = 0; $i < \count($this->migrations_paths); $i++)
            $this->migrations_paths[$i] = \Mii::resolve($this->migrations_paths[$i]);

        foreach ($this->migrations_paths as $migrations_path) {

            if (!is_dir($migrations_path)) {
                $this->warning('Directory :dir does not exist', [':dir' => $migrations_path]);
                mkdir($migrations_path, 0775);
            }

            $scan = scandir($migrations_path);
            foreach ($scan as $file) {
                if ($file[0] == '.')
                    continue;

                $info = pathinfo($file);

                if ($info['extension'] !== 'php')
                    continue;

                $name = $info['filename'];

                $this->migrations_list[$name] = [
                    'name' => $name,
                    'file' => $migrations_path . '/' . $file,
                    'applied' => isset($this->applied_migrations[$name]),
                    'date' => 0
                ];
            }

        }

        uksort($this->migrations_list, 'strnatcmp');

    }


    /**
     * Create new migration file
     */
    public function create(string $name = null)
    {

        $custom_name = false;

        if (count($this->request->params) && $name === null)
            $name = $this->request->params[0];

        if ($name) {
            $custom_name = mb_strtolower($name, 'utf-8');
        }

        DB::begin();
        try {
            $name = 'm' . gmdate('ymd_His');
            if ($custom_name) {
                $name .= '_' . $custom_name;
            }

            $file = '<?php
// ' . strftime('%F %T') . '

use mii\db\DB;

class ' . $name . ' {

    public function up() {

    }

    public function down() {
        return false;
    }
    
    public function safe_up() {

    }

    public function safe_down() {
        return false;
    }

}
';
            reset($this->migrations_paths);
            file_put_contents(current($this->migrations_paths) . '/' . $name . '.php', $file);

            DB::commit();

            $this->info('migration :name created', [':name' => $name]);

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

    }

    /**
     * Apply new migrations
     */
    public function up($limit = null)
    {

        $applied = 0;

        $migrations = $this->migrations_list;

        $limit = (int)$limit;

        if ($limit > 0) {
            $migrations = \array_slice($migrations, 0, $limit);
        }

        foreach ($migrations as $migration) {

            if ($migration['applied'])
                continue;

            $name = $migration['name'];

            $this->info('Loading migration #:name', [':name' => $name]);

            $obj = $this->loadMigration($migration);
            if ($obj->up() === false) {
                $this->error('Migration #:name failed. Stop.', [':name' => $name]);
                return;
            }

            DB::begin();
            try {
                $obj->safe_up();
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollback();
            }

            DB::insert('INSERT INTO`' . $this->migrate_table . '`(`name`, `date`) VALUES(:name, :date)',
                [
                    ':name' => $name,
                    ':date' => time()
                ]);

            $this->info('Migration up successfully', [':name' => $name]);
            $applied++;

        }
        if (!$applied) {
            $this->warning('No new migration found');
        }
    }


    protected function loadMigration($migration)
    {
        require_once($migration['file']);
        return new $migration['name'];
    }

}
