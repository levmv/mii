<?php

namespace mii\web;

use Mii;
use mii\util\Profiler;

class Blocks
{

    protected static $_instance = NULL;

    protected $_blocks = [];

    protected $_used_blocks = [];

    protected $libraries = [
        APP_PATH.'blocks'
    ];

    protected $merge = false;

    protected $frozen_mode = false;

    protected $assets_dir = PUB_PATH.'assets';

    protected $assets_pub_dir = 'assets';


    /**
     * Returns a new Blocks object.
     *
     * @param  array $params Array of settings
     * @return Blocks
     */
    public static function instance(array $config = [])
    {
        if (!static::$_instance) {
            static::$_instance = new Blocks(Mii::$app->config('blocks') + $config);
        }

        return static::$_instance;
    }


    /**
     * Create a new block, or get an existing block
     * @static
     * @param $name Block name
     * @param bool $values Block params
     * @param bool $id Block id
     * @return \mii\web\Block
     */


    public function get($name, array $values = null)
    {
        if (isset($this->_blocks[$name]))
            return $this->_blocks[$name];

        $this->_blocks[$name] = new Block($name, $values);

        return $this->_blocks[$name];
    }


    public function find_block_file($path)
    {

        foreach ($this->libraries as $library_path) {
            if (is_file($library_path . $path . '.php')) {
                return $library_path . $path . '.php';
            }
        }
    }


    /**
     * Recursively process a block and its dependencies
     *
     * @param $block_name
     * @param $files link to assets files array
     * @return bool|string
     */

    public function process_block_assets($block_name, &$files) {

        if (isset($this->_used_blocks[$block_name]))
            return false;

        $block = $this->_blocks[$block_name];


        $depends = $block->get_depends();

        $actual_depends = [];

        if (count($depends)) {

            foreach ($depends as $depend) {

                if (isset($this->_used_blocks[$depend]))
                    continue;

                $actual_depends[] = $depend;

                $this->process_block_assets($depend, $files);

                $this->_used_blocks[$depend] = true;
            }
        }

        $path = explode('_', $block_name);
        $path = implode('/', $path);
        if ($path)
            $path .= '/';

        $types = ['css', 'js'];

        $empty_block = true;

        foreach ($this->libraries as $base_path) {

            if (!is_dir($base_path))
                continue;

            foreach ($types as $type) {

                if (is_file($base_path . $path . $block_name . '.' . $type)) {
                    $files[$type][$block_name . '.' . $type] = $base_path . $path . $block_name . '.' . $type;
                    $empty_block = false;
                }
            }

            if (is_dir($base_path . $path .  'assets')) {
                $files['assets'] = [$block_name => $base_path . $path .  'assets'];
                $empty_block = false;
            }

        }

        if(empty($actual_depends) AND $empty_block)
            return false;

        return $block_name . count($actual_depends) . mb_strlen(implode('', $actual_depends));
    }


    public function find_assets($name, &$files)
    {
        /*foreach ($this->_assets as $type => $list) {
            foreach ($list as $item)
                $files[$type][] = $item;
        }*/

        $path = explode('_', $name);
        $path = implode('/', $path);
        if ($path)
            $path .= '/';

        $_files = [];

        $_media = [];

        $used = [];

        $types = ['css', 'js'];

        foreach ($this->libraries as $base_path) {

            if (!is_dir($base_path))
                continue;

            foreach ($types as $type) {

                if (is_file($base_path . $path . $name . '.' . $type)) {
                    $files[$type][$name . '.' . $type] = $base_path . $path . $name . '.' . $type;
                }
            }

        }

        return;


        $need_keys = [
            ['name' => $full_name . '.css', 'type' => 'css'],
            ['name' => $full_name . '.js', 'type' => 'js']];

        foreach ($need_keys as $need) {
            if (isset($_files[$need['name']])) {
                $files[$need['type']][] = [
                    'path'   => $_files[$need['name']],
                    'name'   => $need['name'],
                    'remote' => false,
                ];
            }
        }

        if (count($_media))
            if ($this->name)
                $files['media'][$this->name] = $_media;

    }


    public function __construct(array $config = [])
    {
        foreach ($config as $key => $value)
            $this->$key = $value;

    }

    /**
     * Magic method, returns the output of [Blocks::render_assets].
     *
     * @return  string
     * @uses    View::render
     */
    public function __toString()
    {
        try {
            return $this->render_assets();
        } catch (\Exception $e) {

            /**
             * Display the exception message.
             *
             * We use this method here because it's impossible to throw and
             * exception from __toString().
             */

            return Exception::handler($e)->send();
        }
    }


    public function render_assets($force = false)
    {

        $groups = [];

        if (MII_PROF) {
            $benchmark = Profiler::start('Mii', __FUNCTION__);
        }

        // Пройдем по списку блоков, формируя группы ресурсов
        // Каждая группа именуется именем основного блока

        foreach ($this->_blocks as $block_name => $block) {

            //if (isset($used_blocks[$block_name]))
              //  continue;

/*            if (!isset($groups[$block_name])) {
                $groups[$block_name] = [
                    'name'   => $block_name,
                    'assets' => ''
                ];
            }*/

            $assets = [];

            $group_name = $this->process_block_assets($block_name, $assets);
            if($group_name !== false) {
                $groups[$group_name] = $assets;
            }

        }

        $out = ['css' => '', 'js' => ''];

        foreach ($groups as $group_name => $group) {
            foreach ($group as $type => $files) {

                if (count($files)) {
                    if ($type == 'assets') {

                        if (!$this->frozen_mode) {
                                $this->_build_assets_dir($files);
                        }

                    } else {
                        $out[$type] .= $this->_build_group($group_name, $type, $files);
                    }
                }
            }

        }


        if (MII_PROF) {
            Profiler::stop($benchmark);
        }

        return $out['css'].$out['js'];
    }


    private function _build_group($group_name, $type, $files)
    {
        $out = [];

        /*        foreach ($files as $file) {
                    if($file['remote']) {
                        $out[] = $this->_gen_html($file['path'], $type);
                    }
                }*/

        if ($this->merge) {
            $output = $this->web_path($type, $group_name . '.' . $type);
            $need_recompile = false;


            $has_files = false;

            foreach ($files as $name => $file) {

                if (!$this->frozen_mode) {
                    if ($this->is_modified_later(PUB_PATH . $output, filemtime($file))) {
                        $need_recompile = true;
                        break;
                    }
                }
                $has_files = true;
            }

            if ($need_recompile) {

                $tmp = '';
                foreach ($files as $file) {
                    $tmp .= ' ' . file_get_contents($file);
                }
                $tmp = $this->_process($tmp, $type);
                file_put_contents(PUB_PATH . $output, $tmp);
                $has_files = true;
            }
            if ($has_files)
                $out[] = $this->_gen_html($output . '?' . filemtime(PUB_PATH . $output), $type);

        } else {

            foreach ($files as $name => $file) {
                //if($file['remote'])
                //  continue;

                $output = $this->web_path($type, $name);

                $mtime = filemtime($file);

                if ($this->is_modified_later(PUB_PATH . $output, $mtime)) {
                    try {
                        copy($file, PUB_PATH . $output);
                    } catch (Exception $e) {
                        $dir = pathinfo(PUB_PATH . $output);
                        $dir = $dir['dirname'];
                        try {
                            if (!is_dir($dir)) {
                                mkdir($dir, 0777, true);
                            } else
                                chmod($dir, 0777);
                            copy($file['path'], PUB_PATH . $output);
                        } catch (Exception $e) {
                        }
                    }
                }
                $out[] = $this->_gen_html($output . '?' . $mtime, $type);
            }

        }


        return implode("\n", $out);
    }


    function _gen_html($link, $type)
    {
        switch ($type) {
            case 'css':
                return '<link type="text/css" href="' . $link . '" rel="stylesheet" />';
            case 'js':
                return '<script src="'.$link.'"></script>';
        }
    }

    function _process($content, $type)
    {
        switch ($type) {
            case 'css':
                return $this->_process_css($content);
            case 'js':

        }

        return $content;
    }

    private function _process_css_file($input)
    {
        return $this->_process_css(file_get_contents($input));
    }


    private function _process_css($content)
    {
        return $content;
        //include_once SYSPATH . 'vendor/cssmin/cssmin/cssmin-v3.0.1.php';

        //return \cssmin::minify($content);
    }


    static public function is_modified_later($file, $source_modified_time)
    {
        return
            !is_file($file)
            ||
            filemtime($file) < $source_modified_time;
    }

    public function file_path($type, $file)
    {
        $file = substr($file, 0, strrpos($file, $type)) . $type;

        return DOCROOT . $this->input_folder . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $file;
    }

    public function web_path($type, $file)
    {
        $file = trim($file, '/');
        $file = str_replace('/', '__', $file);

        return $this->assets_pub_dir . '/' . $file;
    }

    private function _build_assets_dir($media)
    {
     //   $dir = trim($dir, "\/");

        $blockname = key($media);
        $path = current($media);

        $output = $this->assets_dir.'/'. $blockname . '/' ;


        $this->_copy_dir($path, $output);


        return;
        foreach ($files as $dir => $file) {
            if (is_array($file)) {

                $this->_build_assets_dir($blockname, $file);
                continue;
            };

            $dir = trim($dir, "\/");

            $output = $this->_params['assets_dir'] . '/' . $blockname . '/' . $dir;


            $mtime = filemtime($file);


            if ($this->is_modified_later(DOCROOT . $output, $mtime)) {

                try {
                    if (!copy($file, DOCROOT . $output))
                        throw new \Exception;
                } catch (\Exception $e) {

                    $dir = pathinfo(DOCROOT . $output);
                    $dir = $dir['dirname'];
                    try {
                        ;
                        if (!is_dir($dir)) {
                            mkdir($dir, 0777, true);
                        } else
                            chmod($dir, 0777);
                        copy($file, DOCROOT . $output);
                    } catch (Exception $e) {
                    }
                }
            }
        }
    }

    private function _copy_dir($from, $to) {

        $dir = opendir($from);
        @mkdir($to);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($from . '/' . $file) ) {
                    $this->_copy_dir($from . '/' . $file, $to . '/' . $file);
                }
                else {

                    if ($this->is_modified_later($to . '/' . $file, filemtime($from . '/' . $file))) {
                        copy($from . '/' . $file, $to . '/' . $file);
                    }

                }
            }
        }
        closedir($dir);

    }

}

