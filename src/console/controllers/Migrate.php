<?php /** @noinspection SqlResolve */
declare(strict_types=1);

namespace mii\console\controllers;

use mii\console\Controller;
use mii\db\DatabaseException;
use mii\db\DB;

/**
 * DB migration tool
 * @package mii\console\controllers
 */
class Migrate extends Controller
{
    protected string $migrate_table = 'migrations';

    protected array $migrations_list = [];

    protected array $applied_migrations;

    protected array $paths = [];

    protected function before()
    {
        $config = config('console.migrate', []);

        foreach ($config as $name => $value) {
            $this->$name = $value;
        }

        if (empty($this->paths)) {
            $this->warning("Empty migrations path. Use @app/migration automatically");
            $this->paths = [path('app') . '/migrations'];
        }

        try {
            $this->applied_migrations = DB::select('SELECT `name`, `date` FROM `' . $this->migrate_table . '`')->indexBy('name')->all();
        } catch (\Exception) {
            $this->info("Trying to create table $this->migrate_table");

            DB::update('CREATE TABLE `' . $this->migrate_table . '` (
              `name` varchar(180) NOT NULL,
              `date` int(11),
               PRIMARY KEY (`name`)
            );');

            $this->applied_migrations = [];
        }

        for ($i = 0; $i < \count($this->paths); $i++) {
            $this->paths[$i] = \Mii::resolve($this->paths[$i]);
        }

        foreach ($this->paths as $migrations_path) {
            if (!\is_dir($migrations_path)) {
                $this->warning("Directory $migrations_path does not exist");
                if (!mkdir($migrations_path, 0775) && !is_dir($migrations_path)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $migrations_path));
                } else {
                    $this->info("Created directory $migrations_path");
                }
            }

            $scan = \scandir($migrations_path);

            foreach ($scan as $file) {
                if ($file[0] === '.') {
                    continue;
                }

                $info = \pathinfo($file);

                if (!\in_array($info['extension'], ['sql', 'php'])) {
                    continue;
                }

                $name = $info['filename'];

                $this->migrations_list[$name] = [
                    'name' => $name,
                    'type' => $info['extension'],
                    'file' => $migrations_path . '/' . $file,
                    'applied' => isset($this->applied_migrations[$name]),
                    'date' => 0,
                ];
            }
        }

        \uksort($this->migrations_list, 'strnatcmp');
    }

    /**
     * Create new migration file
     * @param string|null $name
     * @param bool $php
     * @throws \Throwable
     */
    public function create(string $name = null, bool $php = false)
    {
        $custom_name = false;

        if (\count($this->request->params) && $name === null) {
            $name = $this->request->params[0];
        }

        if ($name) {
            $custom_name = \mb_strtolower($name);
        }

        $extension = $php ? 'php' : 'sql';

        try {
            $name = 'm' . \gmdate('ymd_His');
            if ($custom_name) {
                $name .= '_' . $custom_name;
            }

            $file = $extension === 'php'
                ? '<?php
// ' . \strftime('%F %T') . '

use mii\db\DB;

class ' . $name . '
{
    public function up()
    {

    }

    public function down()
    {
        return false;
    }
    
    public function safe_up()
    {

    }

    public function safe_down()
    {
        return false;
    }
}
'
                : '';

            \reset($this->paths);
            \file_put_contents(\current($this->paths) . '/' . $name . '.' . $extension, $file);

            $this->info("migration $name created");
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Apply new migrations
     * @param int $limit
     * @throws DatabaseException
     * @throws \Throwable
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
            if ($migration['applied']) {
                continue;
            }

            $name = $migration['name'];

            $this->info("Loading migration #$name");

            if ($migration['type'] === 'sql') {
                DB::begin();

                try {
                    \Mii::$app->db->multiQuery(\file_get_contents($migration['file']));
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollback();
                    $this->error("Migration #$name failed. Stop.");
                    throw $e;
                }
            } elseif ($migration['type'] === 'php') {
                require_once $migration['file'];
                $obj = new $migration['name'];

                if ($obj->up() === false) {
                    $this->error("Migration #$name failed. Stop.");
                    return;
                }

                DB::begin();

                try {
                    $obj->safe_up();
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollback();
                }
            }

            DB::insert(
                'INSERT INTO `' . $this->migrate_table . '` (`name`, `date`) VALUES (:name, :date);',
                [
                    ':name' => $name,
                    ':date' => \time(),
                ]
            );

            $this->info('Migration up successfully');
            $applied++;
        }

        if (!$applied) {
            $this->warning('No new migration found');
        }
    }
}
