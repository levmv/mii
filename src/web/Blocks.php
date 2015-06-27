<?php

namespace mii\web;

use Mii;

class Blocks
{
    protected $_blocks = [];

    protected $_block_paths = [];

    protected $_used_blocks = [];

    protected $libraries = [
        APP_PATH.'/blocks'
    ];

    protected $merge = true;

    protected $use_symlink = true;
    protected $freeze_mode = false;

    protected $assets_dir = PUB_PATH.'/assets';

    protected $assets_pub_dir = '/assets';

    protected $css_blocks = [];
    protected $js_blocks = [];



    public function __construct(array $config = [])
    {
        foreach ($config as $key => $value)
            $this->$key = $value;


        if(MII_DEBUG) {
            foreach($this->libraries as $library) {

            }

        }
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

        $block_path = implode('/', explode('_', $name)) . '/';
        $this->_block_paths[$name] = $block_path;

        $block_file = null;

        if (strpos($name, 'i_') !== 0) {

            foreach ($this->libraries as $library_path) {
                if (is_file($library_path . '/'. $block_path . $name . '.php')) {
                    $block_file = $library_path . '/'. $block_path . $name . '.php';
                }
            }
        }

        $this->_blocks[$name] = new Block($name, $block_file, $values);
        return $this->_blocks[$name];
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
            return $this->render();
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


    public function render()
    {
        if($this->freeze_mode) {
            return $this->fm_render();
        }

        if (MII_PROF) {
            $benchmark = \mii\util\Profiler::start('Mii', __FUNCTION__);
        }

        $this->_used_blocks = [];

        // Пройдем по списку блоков, формируя группы ресурсов
        // Каждая группа именуется именем основного блока + счетчик зависимостей

        $groups = [];

        foreach ($this->_blocks as $block_name => $block) {

            $assets = [];

            $group_name = $this->process_block_assets($block_name, $assets);
            if($group_name !== false) {
                $groups[$group_name] = $assets;
            }

        }

        $out = ['css' => '', 'js' => ''];

        foreach ($groups as $group_name => $group) {
            foreach ($group as $type => $files) {

                if ((bool)$files) {
                    if ($type === 'assets') {
                        $this->_build_assets_dir($files);

                    } else {
                        $out[$type] .= $this->_build_group($group_name, $type, $files);
                    }
                }
            }

        }

        if (MII_PROF) {
            \mii\util\Profiler::stop($benchmark);
        }

        return $out['css'].$out['js'];
    }

    /**
     * Recursively process a block and its dependencies
     *
     * @param $block_name
     * @param $files link to assets files array
     * @return bool|string
     */
    public function process_block_assets($block_name, &$files)
    {
        if (isset($this->_used_blocks[$block_name]))
            return false;

        $depends = $this->_blocks[$block_name]->depends();

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
        $path = '/'.$this->_block_paths[$block_name];
        $types = ['css', 'js'];
        $empty_block = true;

        foreach ($this->libraries as $base_path) {

            if (!is_dir($base_path.$path))
                continue;

            foreach ($types as $type) {
                if (is_file($base_path . $path . $block_name . '.' . $type)) {
                    $files[$type][$block_name . '.' . $type] = $base_path . $path . $block_name . '.' . $type;
                    $empty_block = false;
                }
            }
            if (is_dir($base_path . $path . 'assets')) {
                $files['assets'][$block_name] =  $base_path . $path . 'assets';
                $empty_block = false;
            }
        }

        if (empty($actual_depends) AND $empty_block)
            return false;

        // ok, crc32 is much better, but dumb strlen is much faster
        return $block_name . count($actual_depends) . mb_strlen(implode('', $actual_depends));
    }


    private function _build_group($group_name, $type, $files)
    {
        $out = [];

        if(! (bool) $files)
            return '';

        if ($this->merge) {
            $output = $this->assets_pub_dir . '/' . $group_name . '.' . $type;
            $need_recompile = false;
            foreach ($files as $name => $file) {
                if ($this->is_modified_later(PUB_PATH . $output, filemtime($file))) {
                    $need_recompile = true;
                    break;
                }
            }

            if ($need_recompile) {
                $tmp = '';
                foreach ($files as $file) {
                    $tmp .= ' ' . file_get_contents($file);
                }
                $tmp = $this->_process($tmp, $type);

                $gz_output = gzcompress($tmp, 9, ZLIB_ENCODING_GZIP);

                file_put_contents(PUB_PATH . $output, $tmp);
                file_put_contents(PUB_PATH . $output.'.gz', $gz_output);
            }

            return $this->_gen_html($output . '?' . filemtime(PUB_PATH . $output), $type);

        }

        foreach ($files as $name => $file) {

            $output = $this->assets_pub_dir . '/' . $name;

            $mtime = filemtime($file);

            if ($this->is_modified_later(PUB_PATH . ' / '. $output, $mtime)) {
                try {
                    copy($file, PUB_PATH . '/'. $output);
                } catch (\Exception $e) {
                    Mii::error('Cant copy file '.PUB_PATH . '/'. $output, 'mii');
                    $dir = dirname(PUB_PATH . '/' . $output);
                    mkdir($dir, 0777, true);
                    copy($file['path'], PUB_PATH . $output);
                }
            }
            $out[] = $this->_gen_html($output . '?' . $mtime, $type);
        }

        return implode("\n", $out);
    }


    function _gen_html($link, $type)
    {
        switch ($type) {
            case 'css':
                return '<link type="text/css" href="' . $link . '" rel="stylesheet" />';
            case 'js':
                return '<script defer src="'.$link.'"></script>';
        }
        return '';
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


    private function _process_css($content)
    {
        // https://github.com/matthiasmullie/minify

        // strip comments
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);

        // remove leading & trailing whitespace
        $content = preg_replace('/^\s*/m', '', $content);
        $content = preg_replace('/\s*$/m', '', $content);
        // replace newlines with a single space
        $content = preg_replace('/\s+/', ' ', $content);
        // remove whitespace around meta characters
        // inspired by stackoverflow.com/questions/15195750/minify-compress-css-with-regex
        $content = preg_replace('/\s*([\*$~^|]?+=|[{};,>~]|!important\b)\s*/', '$1', $content);
        $content = preg_replace('/([\[(:])\s+/', '$1', $content);
        $content = preg_replace('/\s+([\]\)])/', '$1', $content);
        $content = preg_replace('/\s+(:)(?![^\}]*\{)/', '$1', $content);
        // whitespace around + and - can only be stripped in selectors, like
        // :nth-child(3+2n), not in things like calc(3px + 2px) or shorthands
        // like 3px -2px
        $content = preg_replace('/\s*([+-])\s*(?=[^}]*{)/', '$1', $content);
        // remove semicolon/whitespace followed by closing bracket
        $content = trim(preg_replace('/;}/', '}', $content));

        // Shorthand hex color codes.
        $content = preg_replace('/(?<![\'"])#([0-9a-z])\\1([0-9a-z])\\2([0-9a-z])\\3(?![\'"])/i', '#$1$2$3', $content);


        return $content;
    }


    static public function is_modified_later($file, $source_modified_time)
    {
        return
            !is_file($file)
            ||
            filemtime($file) < $source_modified_time;
    }


    private function _build_assets_dir($media)
    {
        $blockname = key($media);
        $path = current($media);

        $output = $this->assets_dir.'/'. $blockname ;


        if($this->use_symlink) {
            if(!is_link($output)) {
                symlink($path, $output);
            }
        } else {
            $this->_copy_dir($path, $output);
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


    public function fm_render() {

        if (MII_PROF) {
            $benchmark = \mii\util\Profiler::start('Mii', __FUNCTION__);
        }

        $css = '';
        $js = '';

        foreach ($this->_blocks as $block_name => $block) {

            if(isset($this->css_blocks[$block_name])) {
                $css .= $this->_gen_html($this->assets_pub_dir.'/'.$block_name.'.css?r'.$this->revision, 'css');
            }

            if(isset($this->js_blocks[$block_name])) {
                $js .= $this->_gen_html($this->assets_pub_dir.'/'.$block_name.'.js?r'.$this->revision, 'js');
            }
        }

        if (MII_PROF) {
            \mii\util\Profiler::stop($benchmark);
        }

        return $css.$js;
    }


}

