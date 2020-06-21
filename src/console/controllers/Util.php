<?php declare(strict_types=1);

namespace mii\console\controllers;

use mii\console\Controller;
use mii\db\DB;
use mii\util\Debug;

/**
 * Various utils
 *
 * @package mii\console\controllers
 */
class Util extends Controller
{
    public function preload(string $fcgi = null, bool $save = false)
    {
        if (!$fcgi) {
            $this->warning('No --fcgi option specified. Trying to detect php-fpm listen addr...');
            $fcgi = $this->detectFcgiListen();

            if (!$fcgi) {
                $this->error('Detect failed. Exit');
                return;
            }
            $this->info("Detected: $fcgi");
        }

        $tmp = \tempnam('/tmp', 'ops') . '.php';
        \file_put_contents(
            $tmp,
            <<<EOS
        <?php echo json_encode(opcache_get_status(true));
        EOS
        );

        $result = [];

        \exec("REQUEST_METHOD=GET SCRIPT_FILENAME=$tmp cgi-fcgi -bind -connect $fcgi $tmp", $result, $code);

        \unlink($tmp);

        if ($code !== 0) {
            $this->error($result);
            return;
        }
        // Strip headers
        $result = \implode("\n", $result);
        $result = \substr($result, \strpos($result, "\n\n") + 2);

        try {
            $result = \json_decode($result, true);
        } catch (\Throwable $t) {
            $this->error($t);
            return;
        }

        $this->info(<<<EOS
        ---
        Opcache enabled:  {opcache}
        Used memory:      {usedmem}Mb
        Free memory:      {freemem}Mb
        Cached scripts:   {cached} (max: {max})
        Total hits:       {hits}
        EOS, [
            '{opcache}' => $result['opcache_enabled'] ? 'True' : 'False',
            '{usedmem}' => \number_format($result['memory_usage']['used_memory'] / 1024 / 1024, 2),
            '{freemem}' => \number_format($result['memory_usage']['free_memory'] / 1024 / 1024, 2),
            '{cached}' => $result['opcache_statistics']['num_cached_scripts'],
            '{max}' => $result['opcache_statistics']['max_cached_keys'],
            '{hits}' => $result['opcache_statistics']['hits'],
        ]);

        $scripts = [];
        $blocks = [];


        foreach ($result['scripts'] as $key => ['full_path' => $path,
                 'hits' => $hits,
                 'memory_consumption' => $mem, ]) {
            if ($hits === 0) {
                continue;
            }

            if ($path === path('root') . '/index.php') {
                continue;
            }

            if (\strpos($path, 'composer') !== false) {
                continue;
            }
            if (\strpos($path, 'preload.php') !== false) {
                continue;
            }

            if (\strpos($path, 'blocks') !== false) {
                $blocks[$path] = [$hits, $mem];
            } else {
                $scripts[$path] = [$hits, $mem];
            }
        }

        \uasort($scripts, static function ($a, $b) {
            return $b[0] <=> $a[0];
        });

        $this->info("\nTop scripts:\nhits   mem, kb  path\n--------------------------");
        foreach ($scripts as $path => [$hits, $mem]) {
            $mem = \number_format($mem / 1024, 2);

            $this->info(
                \str_pad($hits, 7) . ' ' .
                \str_pad($mem, 6, ' ', \STR_PAD_LEFT) . '  ' .
                Debug::path($path)
            );
        }

        $this->info('Blocks:');
        foreach ($blocks as $path => $hits) {
            $this->info(\str_pad($hits, 5) . ' ' . $path);
        }

        if ($save) {
            $array = \var_export(\array_keys($scripts), true);

            \file_put_contents(
                path('root') . '/preload.php',
                <<<"EOS"
            <?php
            
                require(__DIR__ . '/vendor/autoload.php');
                               
                \$scripts = $array;
                
                foreach(\$scripts as \$filename) 
                    require_once(\$filename);                                      
            EOS
            );
        }
    }


    private function detectFcgiListen()
    {
        $output = [];
        \exec("ps aux | grep \"php-fpm\" | awk '{print $11}'", $output, $code);

        if ($code !== 0 || !\count($output)) {
            return null;
        }

        $fpm = $output[0];
        $output = [];
        \exec("$fpm -tt 2>&1 | grep 'listen = '", $output);

        if (!\count($output)) {
            return null;
        }

        \preg_match('/listen\s*=\s*(.*)/s', $output[0], $matches);

        if (\count($matches) !== 2) {
            return null;
        }

        return $matches[1];
    }


    /**
     * Print table columns as phpdoc properties
     * @param $name
     */
    public function table(string $name)
    {
        $table_info = DB::select('SHOW FULL COLUMNS FROM ' . $name)->toArray();

        $columns = [];
        foreach ($table_info as $info) {
            $column = [];

            $info = \array_change_key_case($info, \CASE_LOWER);

            $column_name = $info['field'];
            $column['allow_null'] = $info['null'] === 'YES';
            $column['type'] = $info['type'];

            if (\preg_match('/^(\w+)(?:\(([^)]+)\))?/', $info['type'], $matches)) {
                $column['type'] = $this->convertTypeNames(\strtolower($matches[1]));

                $type = \strtolower($matches[1]);


                if (!empty($matches[2])) {
                    if ($type === 'enum') {
                    } else {
                        $values = \explode(',', $matches[2]);
                        $column['size'] = (int) $values[0];
                        if (isset($values[1])) {
                            $column['scale'] = (int) $values[1];
                        }
                        if ($column['size'] === 1 && $type === 'bit') {
                            $column['type'] = 'boolean';
                        } elseif ($type === 'bit') {
                            if ($column['size'] > 32) {
                                $column['type'] = 'bigint';
                            } elseif ($column['size'] === 32) {
                                $column['type'] = 'integer';
                            }
                        }
                    }
                }
            }
            $columns[$column_name] = $column;
        }

        foreach ($columns as $column_name => $column) {
            $this->stdout('* @property ' . \str_pad($column['type'], 7) . '$' . $column_name . "\n");
        }
    }

    private function convertTypeNames(string $type): string
    {
        if ($type === 'int' || $type === 'smallint' || $type === 'tinyint') {
            return 'int';
        }

        if ($type === 'bigint') {
            return 'bigint';
        }

        if ($type === 'double' || $type === 'float') {
            return 'float';
        }

        return 'string';
    }
}
