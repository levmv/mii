<?php

namespace mii\console\controllers;


use Mii;
use mii\console\CliException;
use mii\console\Controller;
use mii\util\Console;
use mii\util\FS;
use mii\util\Text;

class Assets extends Controller
{
    public $description = 'Blocks assets builder';

    public $config_file;
    private $json_output;
    private $force_mode;
    private $filtered_sets;

    private $assets;
    private $libraries;

    private $base_path;
    private $base_url = '/assets';

    private $assets_map_path;

    protected $assets_group = 'static';

    private $results = [];

    private $processed = [
        'css' => [],
        'js' => [],
    ];

    protected function before() {

        $this->_time = microtime(true);
        $this->_memory = memory_get_usage();

        $this->input_path = \path('vendor') . '/node_modules/';
        $this->output_path = \path('app') . '/blocks';

        $this->sets = config('components.blocks.sets');

        $this->assets_map_path = config('components.blocks.assets_map_path', '@tmp');

        $this->config_file = $this->request->params['config'] ?? '@app/config/assets.php';
        $this->json_output = $this->request->params['json'] ?? false;
        $this->force_mode = $this->request->params['force'] ?? false;
        $this->filtered_sets = (array) ($this->request->params['set'] ?? array_keys($this->sets));

        if(!file_exists(Mii::resolve($this->config_file))) {
            $this->error("Config {$this->config_file} does not exist.");
            exit(1);
        }

        $this->assets = require(Mii::resolve($this->config_file));
    }

    public function usage($argv) {
        $this->stdout(
            "\nUsage: ./mii assets (build|test|gen-config) [options]\n\n" .
            "Options:\n" .
            " --config=<path>\tPath to configuration file. By default it's «@app/config/assets.php»\n" .
            " --set=<setname>\tName of set to process\n" .
            " --force\tDont check if files changed\n" .
            " --stdout\tTo print assets paths to stdout\n" .
            "\n\n",
            Console::FG_YELLOW
        );
        return;
    }


    public function test() {

        if(count($this->sets)) {

            foreach($this->sets as $name => $set) {
                $this->test_set($name);
            }
        } else {
            $this->test_set('default');
        }

    }

    public function build() {

        if(count($this->sets)) {

            foreach($this->filtered_sets as $name) {
                $this->build_set($name);
            }
        } else {
            $this->build_set('default');
        }

        foreach($this->results as $set_name => $list) {
            file_put_contents(
                $this->assets_map_path."/".$set_name.'.assets',
                "<?php return ".var_export($list, true).';'
            );
        }

        if($this->json_output) {
            $this->stdout(json_encode($this->processed));
            return;
        }


        if(count($this->processed['css']) || count($this->processed['js']) ){
            $this->info(':N css and :M js files compiled.', [
                ':N' => count($this->processed['css']),
                ':M' => count($this->processed['js'])
            ]);
        } else {
            $this->info('No recompiled files. No need to regenerate config');
        }

        $this->info('All done. Spent :Ts and :MMb', [
            ':T' => number_format(microtime(true) - $this->_time, 1),
            ':M' => number_format((memory_get_usage()-$this->_memory) / 1024 / 1024, 1)
        ]);
    }


    protected function test_set($set_name) {
        $this->info("==========================");
        $this->info("Testing of set «:name»", [":name" => $set_name]);
        $this->info("==========================");

        $this->init_set($set_name);

        $blocks = [];

        foreach($this->libraries as $library) {

            if(!is_dir($library)) {
                $this->error('Library path «'.$library.'» does not exist');
                return;
            }

            $directory = new \RecursiveDirectoryIterator($library);
            $filter = new \RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) {
                // Skip hidden files and directories.
                if ($current->getFilename()[0] === '.') {
                    return FALSE;
                }
                if ($current->isDir()) {
                    // Only recurse into intended subdirectories.
                    return $current->getFilename() !== 'assets';
                }

                return $current->getExtension() !== 'php';
            });
            $iterator = new \RecursiveIteratorIterator($filter);

            foreach ($iterator as $info) {

                $type = $info->getExtension();
                $path = trim(substr( $info->getPath(),strlen($library)), '/');
                $block_name = implode('_', explode('/', $path));
                if(!isset($blocks[$block_name])) {
                    $blocks[$block_name] = ['css' => false, 'js' => false, 'path' => []];
                }
                $blocks[$block_name][$type] = true;
                $blocks[$block_name]['path'][] = $info->getPath();
            }
        }

        $reverse = ['css' => [], 'js' => []];

        if(!isset($this->assets[$this->assets_group])) {
            $this->error('Source list with name «'.$this->assets_group.'» does not exist');
            return;
        }

        foreach ($this->assets[$this->assets_group] as $name => $file) {
            foreach (['css', 'js'] as $type) {
                if (isset($file[$type])) {
                    if (!is_array($file[$type]))
                        $file[$type] = (array)$file[$type];

                    foreach ($file[$type] as $block) {

                        if (isset($reverse[$type][$block])) {
                            $this->error('Double declaration of :b (in «:f1» and «:f2» files)', [
                                ':b' => $block,
                                ':f1' => $reverse[$type][$block],
                                ':f2' => $name
                            ]);
                        }
                        $reverse[$type][$block] = $name;
                    }
                }
            }
        }

        $forget = [];
        foreach ($blocks as $block_name => $block) {
            foreach (['css', 'js'] as $type) {
                if($block[$type] && !isset($reverse[$type][$block_name])) {
                    $forget[$block_name][] = "'".$type . "' => '" . $block_name ."'";
                }
            }
        }

        if(count($forget)) {
            $this->warning('May be you forget these:');
            foreach($forget as $name => $block) {
                $out = "'$name' => [";
                $out .= implode(',',  $block);

                $this->stdout("$out],\n");
            }

        }
    }


    protected function init_set($set_name) {

        $this->base_path = null;
        $this->libraries = [];

        $default_set = [
            'libraries' => [],
            'base_url' => $this->base_url,
            'base_path' => $this->base_path
        ];

        if(!isset($this->sets[$set_name]) && $set_name === 'default') {
            $set = [];
        } elseif(isset($this->sets[$set_name])) {
            $set = $this->sets[$set_name];
        } else {
            throw new CliException("Unknow blocks set name: $set_name");
        }

        $set = array_replace_recursive($default_set, $set);

        foreach($set as $key => $value)
            $this->$key = $value;

        for ($i = 0; $i < count($this->libraries); $i++)
            $this->libraries[$i] = Mii::resolve($this->libraries[$i]);

        $this->base_url = Mii::resolve($this->base_url);

        if ($this->base_path === null) {
            $this->base_path = \path('pub') . $this->base_url;
        } else {
            $this->base_path = Mii::resolve($this->base_path);
        }

        $this->assets_map_path = Mii::resolve($this->assets_map_path);

        if(!is_dir($this->base_path)) {
            mkdir($this->base_path, 0777, true);
        }

        if(!isset($this->assets[$this->assets_group])) {
            $this->error('Assets group «'.$this->assets_group.'» does not exist');
        }
    }


    protected function build_set($set_name) {
        $this->init_set($set_name);

        $this->results[$set_name] = [];
        foreach($this->assets[$this->assets_group] as $filename => $data) {
            foreach (['css', 'js'] as $type) {
                if(!isset($data[$type]))
                    continue;

                if(!is_array($data[$type]))
                    $data[$type] = (array) $data[$type];

                $result_file_name = $this->build_file($filename, $data[$type], $type);

                foreach($data[$type] as $block_name) {
                    $this->results[$set_name][$type][$block_name] = $result_file_name;
                }
            }
        }
    }


    protected function build_file($filename, $blocks, $type) : string {

        $out_path = $this->base_path .'/';

        $files = [];

        $hashes = '';

        foreach($blocks as $block_name) {

            $block_path = '/' . implode('/', explode('_', $block_name)) ;
            $block_file = $block_path . '/' . $block_name;

            foreach ($this->libraries as $library_path) {
                $result_filename = Mii::resolve($library_path) . $block_file . '.' . $type;

                if (is_file($result_filename)) {

                    $files[] = $result_filename;
                    $hashes .= sha1_file($result_filename, true) . pack('L', filesize($result_filename));

                    break;
                }
            }
            // Can't merge with previous cycle because of the break
            foreach ($this->libraries as $library_path) {
                $library_path = Mii::resolve($library_path);

                if (is_dir($library_path . $block_path . '/assets')) {

                    $output = $this->base_path .'/'.$block_name;

                    if (!is_link($output)) {
                        symlink($library_path . $block_path . '/assets', $output);
                    }
                    break;
                }
            }
        }

        $outname = $filename.$this->hash($hashes);

        if(!empty($files)) {
            if(!file_exists($out_path.$outname.'.'.$type) || $this->force_mode) {
                $this->processed[$type][] = $this->merge_files_to_one($files, $out_path, $outname .'.'.$type);
            }
        }

        return $outname;
    }


    protected function hash(string $str) : string {
        return Text::base64url_encode(hash('fnv1a64', $str, true)) .
            Text::base64url_encode(hash('crc32b', $str, true));
    }


    protected function merge_files_to_one($files, $path, $filename) {

        $tmp = '';

        foreach ($files as $file) {
            $tmp .= file_get_contents($file) . "\n";
        }

        if(!is_dir($path)) {
            FS::mkdir($path, 0777, true);
        }

        file_put_contents($path.$filename, $tmp);

        if(!$this->json_output) {
            $this->info(':block compiled.', [':block' => $path.$filename]);
        }

        return $path.$filename;
    }

}