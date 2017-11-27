<?php

namespace mii\console\controllers;


use Mii;
use mii\console\CliException;
use mii\console\Controller;
use mii\util\Console;

class Assets extends Controller
{

    public $description = 'Blocks assets builder';

    private $blocks;
    private $libraries;

    private $base_path;
    private $base_url = '/assets';

    protected $static_source = 'static';

    private $results = [];

    private $processed = [
        'css' => [],
        'js' => [],
    ];

    public function before() {

        $this->_time = microtime(true);
        $this->_memory = memory_get_usage();

        $this->input_path = path('vendor') . '/bower/';
        $this->output_path = path('app') . '/blocks';

        $this->sets = config('components.blocks.sets');

        if(!file_exists(path('app').'/config/assets.php')) {
            $this->error('Config app/config/assets.php does not exist.');
            exit(1);
        }

        $assets_config = require(path('app').'/config/assets.php');
        foreach($assets_config as $static_source_name => $static_source)
            $this->$static_source_name = $static_source;

        $this->blocks = config('components.blocks.blocks');
        $this->libraries = config('components.blocks.libraries',
            [path('app') . '/blocks']
        );

        $this->pubdir = config('components.blocks.base_url');

        $this->gen_config_path = path('root').'/assets.compiled';

        $this->default_set = [
            'libraries' => [
                path('app') . '/blocks'
            ],
            'base_url' => '/assets/d',
            'base_path' => null
        ];

    }

    public function index() {
        $this->stdout(
            "\nUsage: ./mii assets (build|test|gen-config) [options]\n\n" .
            "Options:\n" .
            " ——config=<path>\tPath to configuration file. By default it's «@app/config/assets.php»\n" .
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

            foreach($this->sets as $name => $set) {
                $this->build_set($name);
            }
        } else {
            $this->build_set('default');
        }

        $this->info(':N css and :M js files compiled.', [
            ':N' => count($this->processed['css']),
            ':M' => count($this->processed['js'])
        ]);

        if(count($this->processed['css']) || count($this->processed['js']) ){
            $this->info('Start post processing...');
            $this->post_process();


            foreach($this->results as $source => $list) {
                file_put_contents(
                    path('root')."/".$source.'.assets',
                        "<?php return ".var_export($list, true).';'
                    );
            }

        } else {

            $this->info('No recompiled files. No need to regenerate config');
        }


        $this->info('All done. Spent :Ts and :MMb', [
            ':T' => number_format(microtime(true) - $this->_time, 1),
            ':M' => number_format((memory_get_usage()-$this->_memory) / 1024, 1)
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

        if(!isset($this->{$this->static_source})) {
            $this->error('Source list with name «'.$this->static_source.'» does not exist');
            return;
        }

        foreach ($this->{$this->static_source} as $name => $file) {
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


                $this->stdout("$out]\n");
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
            throw new ErrorException("Unknow blocks set name: $set_name");
        }

        $set = array_replace_recursive($default_set, $set);

        foreach($set as $key => $value)
            $this->$key = $value;

        for ($i = 0; $i < count($this->libraries); $i++)
            $this->libraries[$i] = Mii::resolve($this->libraries[$i]);

        $this->base_url = Mii::resolve($this->base_url);

        if ($this->base_path === null) {
            $this->base_path = path('pub') . $this->base_url;
        } else {
            $this->base_path = Mii::resolve($this->base_path);
        }

        if(!is_dir($this->base_path)) {
            mkdir($this->base_path, 0777, true);
        }

        if(!isset($this->{$this->static_source})) {
            $this->error('Static source «'.$this->static_source.'» does not exist');
        }
    }


    protected function build_set($set_name) {
        $this->init_set($set_name);

        $this->results[$set_name] = [];

        foreach($this->{$this->static_source} as $name => $block) {
            $this->build_block($set_name, $name, $block);
        }
    }


    protected function build_block($set_name, $name, $data) {

        foreach (['css', 'js'] as $type) {
            if(isset($data[$type])) {

                $files = [];

                if(!is_array($data[$type]))
                    $data[$type] = (array) $data[$type];

                $outpath = $this->base_path .'/';
                $names = [];

                foreach($data[$type] as $block_name) {

                    $block_path = '/' . implode('/', explode('_', $block_name)) ;

                    $block_file = $block_path . '/' . $block_name;

                    foreach ($this->libraries as $library_path) {
                        $library_path = Mii::resolve($library_path);

                        if (is_file($library_path . $block_file . '.' . $type)) {
                            $files[] = $library_path . $block_file . '.' . $type;

                            $names[] = $block_name .
                                        filemtime($library_path . $block_file . '.' . $type);
                            break;
                        }
                    }

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

                $outname = $name.$this->hash(implode(',', $names));


                if(!empty($files)) {
                    if(!file_exists($outpath.$outname.'.'.$type)) {
                        $this->processed[$type][] = $this->process_files($files, $outpath, $outname .'.'.$type);
                    }

                    foreach($data[$type] as $block_name) {
                        $this->results[$set_name][$type][$block_name] = $outname;
                    }
                }
            }
        }

    }


    protected function hash(string $str) : string {

        return substr(md5($str), 0,5).
                substr(sha1($str), 0,6);
    }


    protected function process_files($files, $path, $filename) {

        $tmp = '';

        foreach ($files as $file) {
            $tmp .= file_get_contents($file) . "\n";
        }

        if(!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        file_put_contents($path.$filename, $tmp);

        $this->info(':block compiled.', [':block' => $path.$filename]);


        return $path.$filename;
    }


    protected function post_process() {

        if(!is_file(path('root') . '/assets.js')) {
            $this->warning('File @root/assets.js does not exist. Cancel post-processing.');
            return;
        }

        $nodejs = proc_open('node ' . path('root') . '/assets.js',
            array(array('pipe', 'r'), array('pipe', 'w')),
            $pipes
        );
        if ($nodejs === false) {
            $this->error('Could not reach node runtime');
            return;
        }
        $this->fwrite_stream($pipes[0],
            json_encode($this->processed)
            );
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        if($output)
            $this->warning($output);

        fclose($pipes[1]);
        proc_close($nodejs);

    }

    private function fwrite_stream($fp, $string, $buflen = 4096)
    {
        for ($written = 0, $len = strlen($string); $written < $len; $written += $fwrite) {
            $fwrite = fwrite($fp, substr($string, $written, $buflen));
            if ($fwrite === false) {
                return $written;
            }
        }

        return $written;
    }

}