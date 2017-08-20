<?php

namespace mii\console\controllers;


use Mii;
use mii\console\CliException;
use mii\console\Controller;

class Assets extends Controller
{

    public $description = 'Blocks assets builder';

    private $blocks;
    private $libraries;

    private $revision;
    private $base_path;
    private $base_url = '/assets';

    protected $static_source = 'static';

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

        $this->static = config('components.blocks.static');

        $this->blocks = config('components.blocks.blocks');
        $this->libraries = config('components.blocks.libraries',
            [path('app') . '/blocks']
        );
        $this->revision = config('components.blocks.revision', 1);

        $this->pubdir = config('components.blocks.base_url');

        $this->default_set = [
            'libraries' => [
                path('app') . '/blocks'
            ],
            'base_url' => '/assets/d',
            'base_path' => null
        ];

    }


    private function update_revision() {
        $filename = path('root').'/revision.php';

        if(is_file($filename)) {
            $file = file_get_contents($filename);

            preg_match('/(return\s+)(?P<rev>\d+)/i', $file, $matches);

            if(!isset($matches['rev']))
                return false;

            $rev = (int) $matches['rev'] + 1;

            $file = preg_replace('/(return[\s+])(\d+)/m', '${1}'.$rev, $file );

            file_put_contents($filename, $file);

            $this->warning("Revision was auto incremented to $rev\n");

            $this->revision = $rev;
            config_set('components.blocks.revision', $rev);
        }
    }


    public function index() {

        $this->update_revision();

        if(count($this->sets)) {

            foreach($this->sets as $name => $set) {
                $this->process_set($name);
            }
        } else {
            $this->process_set('default');
        }

        $this->info(':N css and :M js files compiled. Start post processing...', [
            ':N' => count($this->processed['css']),
            ':M' => count($this->processed['js'])
        ]);

        $this->post_process();

        $this->info('All done. Spent :Ts and :MMb', [
            ':T' => number_format(microtime(true) - $this->_time, 1),
            ':M' => number_format((memory_get_usage()-$this->_memory) / 1024, 1)
        ]);
    }


    protected function process_set($set_name) {

        $this->base_path = null;

        $default_set = [
            'libraries' => $this->libraries,
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

        if(isset($set['static_source']))
            $this->{$set['static_source']} = config('components.blocks.'.$set['static_source']);

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

        foreach($this->{$this->static_source} as $name => $block) {
            $this->build_block($name, $block);
        }
    }

    protected function build_block($name, $data) {

        foreach (['css', 'js'] as $type) {
            if(isset($data[$type])) {

                $files = [];

                if(!is_array($data[$type]))
                    $data[$type] = (array) $data[$type];

                $outpath = $this->base_path .'/'. $this->revision .'/';
                $outname = $name.'.'.$type;

                foreach($data[$type] as $block_name) {

                    $block_path = '/' . implode('/', explode('_', $block_name)) ;

                    $block_file = $block_path . '/' . $block_name;

                    foreach ($this->libraries as $library_path) {
                        $library_path = Mii::resolve($library_path);

                        if (is_file($library_path . $block_file . '.' . $type)) {
                            $files[] = $library_path . $block_file . '.' . $type;
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

                $this->processed[$type][] = $this->process_files($files, $outpath, $outname);
            }
        }
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

        if(!is_file(path('root') . '/assets.js'))
            return;

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

        $output = json_decode($output, true);
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