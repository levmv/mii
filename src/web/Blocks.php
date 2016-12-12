<?php

namespace mii\web;

use Mii;
use mii\util\HTML;

class Blocks
{
    const HEAD = 0;
    const BEGIN = 1;
    const END = 2;

    public $assets_dir;

    protected $block_class = 'mii\web\Block';

    protected $_blocks = [];

    protected $_block_paths = [];

    protected $_used_blocks = [];

    protected $libraries;

    protected $merge = true;

    protected $process_assets = true;
    protected $use_symlink = true;

    protected $assets_pub_dir = '/assets';

    protected $_rendered = false;

    protected $_css = [];

    protected $_js = [ [],[],[] ];

    protected $_files = [
        '.css' => [
        ],
        '.js' => [
        ],
    ];


    public function __construct(array $config = [])
    {
        foreach ($config as $key => $value)
            $this->$key = $value;

        if(!$this->libraries) {
            $this->libraries = [
                path('app').'/blocks'
            ];
        } else {
            for($i=0; $i<count($this->libraries); $i++)
                $this->libraries[$i] = Mii::resolve($this->libraries[$i]); 
        }

        if(!$this->assets_dir) {
            $this->assets_dir = path('pub').'/assets';
        }
    }

    public function configure($config) {

        foreach ($config as $key => $value)
            $this->$key = $value;
    }


    /**
     * Create a new block, or get an existing block
     * @static
     * @param $name string Block name
     * @return Block
     */


    public function get($name)
    {
        if (isset($this->_blocks[$name]))
            return $this->_blocks[$name];

        $this->_block_paths[$name] = $block_path = '/'.implode('/', explode('_', $name)) . '/';

        $block_file = null;

        if (strpos($name, 'i_') !== 0) {

            $block_path .= $name;

            foreach ($this->libraries as $library_path) {
                if (is_file($library_path . $block_path . '.php')) {
                    $block_file = $library_path . $block_path  . '.php';
                    break;
                }
            }
        }

        $this->_blocks[$name] = new $this->block_class($name, $block_file);

        return $this->_blocks[$name];
    }


    public function css() {
        if(!$this->_rendered)
            $this->render();

        return implode("\n", $this->_css);
    }


    public function js($position = null) {
        if(!$this->_rendered)
            $this->render();

        if($position === null) {
            $out = [];
            foreach($this->_js as $js) {
                if (!empty($js))
                    $out[] = implode("\n", $js);
            }
            return implode("\n", $out);
        }

        return implode("\n", $this->_js[$position]);
    }



    public function render()
    {
        if (config('debug')) {
            $benchmark = \mii\util\Profiler::start('Assets', __FUNCTION__);
        }

        foreach ($this->_blocks as $block_name => $block) {

            if($block->__has_parent)
                continue;

            $this->process_block_assets($block_name, $block_name, $block->_depends);

        }

        foreach($this->_files as $type => $blocks) {
            foreach($blocks as $block_name => $block) {

                if(isset($block['files']))
                    $this->_build_block($block_name, $type, $block['files']);
                if(isset($block['remote'])) {

                    if ($type === '.js') {
                        foreach($block['remote'] as $position => $remote) {
                            $this->_js[$position][] = implode("\n", $remote);
                        }
                    } else {

                        foreach($block['remote'] as $condition => $css_remote) {
                            if($condition) {
                                $this->_css[] = '<!--[if '.$condition.']><link type="text/css" href="' . implode("\n",$css_remote) . '" rel="stylesheet" /><![endif]-->';
                            } else {
                                $this->_css[] = '<link type="text/css" href="' . implode("\n",$css_remote) . '" rel="stylesheet" />';
                            }
                        }
                    }

                }
                if(isset($block['inline'])) {

                    if ($type === '.js') {
                        foreach($block['inline'] as $position => $inline) {
                            $this->_js[$position][] = '<script type="text/javascript">' . implode("\n", $inline) . '</script>';
                        }

                    } else {
                        $content = implode("\n", $block['inline']);
                        $this->_css[] = '<link type="text/css" href="' . $content . '" rel="stylesheet" />';
                    }
                }
            }
        }

        $this->_rendered = true;

        if (config('debug')) {
            \mii\util\Profiler::stop($benchmark);
        }
    }

    /**
     * Recursively process a block and its dependencies
     *
     * @param $block_name
     * @param $files link to assets files array
     * @return bool|string
     */
    public function process_block_assets($block_name, $parent_block, $depends) {
        if (isset($this->_used_blocks[$block_name])) {
            return false;
        }

        if($this->process_assets) {
            foreach ($this->libraries as $base_path) {
                if (is_dir($base_path . $this->_block_paths[$block_name] . 'assets')) {
                    $this->_build_assets_dir($block_name, $base_path . $this->_block_paths[$block_name] . 'assets');
                }
                break;
            }
        }

        if (!empty($depends)) {
            foreach ($this->libraries as $base_path) {
                foreach ($depends as $depend) {
                    //if (!is_dir($base_path . '/' . $this->_block_paths[$depend]))
                        //continue;
                    if (isset($this->_used_blocks[$depend]))
                        continue;
                    $this->process_block_assets($depend, $parent_block, $this->_blocks[$depend]->_depends);
                    $this->_used_blocks[$depend] = true;
                }
            }
        }
        $path = $this->_block_paths[$block_name];
        $types = ['.css', '.js'];

        foreach ($types as $type) {
            foreach ($this->libraries as $base_path) {

                if (is_file($base_path . $path . $block_name . $type)) {
                    $this->_files[$type][$parent_block]['files'][$block_name  . $type] = $base_path . $path . $block_name . $type;
                    break;
                }
            }
        }

        if($this->_blocks[$block_name]->__remote_js !== null) {
            foreach($this->_blocks[$block_name]->__remote_js as $link => $settings) {
                if(!empty($settings) AND isset($settings['position'])) {
                    $position = $settings['position'];
                    unset($settings['position']);
                } else {
                    $position = Blocks::END;;
                }
                if(isset($settings['condition'])) {
                    $condition = $settings['condition'];
                    unset($settings['condition']);
                    $this->_files['.js'][$parent_block]['remote'][$position][] = '<!--[if '.$condition.']>'.HTML::script($link, $settings).'<![endif]-->';
                } else {
                    $this->_files['.js'][$parent_block]['remote'][$position][] = HTML::script($link, $settings);
                }
            }
        }

        if($this->_blocks[$block_name]->__remote_css !== null) {
            if(!isset($this->_files['.css'][$parent_block]['remote']))
                $this->_files['.css'][$parent_block]['remote'] = [];

            foreach($this->_blocks[$block_name]->__remote_css as $r_css => $r_options) {
                $condition = isset($r_options['condition']) ? $r_options['condition'] : '';
                $this->_files['.css'][$parent_block]['remote'][$condition][] = $r_css;
            }
        }

        if(!empty($this->_blocks[$block_name]->__inline_js)) {

            foreach($this->_blocks[$block_name]->__inline_js as $inline) {
                $position = (!empty($inline[1]) AND isset($inline[1]['position'])) ? $inline[1]['position'] : Blocks::END;
                if(!isset($this->_files[$type][$parent_block]['inline'][$position]))
                    $this->_files[$type][$parent_block]['inline'][$position] = [];
                $this->_files[$type][$parent_block]['inline'][$position][] = $inline[0];
            }
        }

        if(!empty($this->_blocks[$block_name]->__inline_css)) {
            if(!isset($this->_files['.css'][$parent_block]['inline']))
                $this->_files['.css'][$parent_block]['inline'] = $this->_blocks[$block_name]->__inline_css;
            else
                $this->_files['.css'][$parent_block]['inline'] = array_merge($this->_files['.css'][$parent_block]['inline'], $this->_blocks[$block_name]->__inline_css);
        }
    }


    private function _build_block($block_name, $type, $files)
    {
        $result_file_name = $block_name.crc32(implode('', array_keys($files)));

        $is_css = ($type === '.css');

        if ($this->merge) {
            $web_output = $this->assets_pub_dir . '/' . $result_file_name . $type;
            $output = path('pub') . $web_output;
            $need_recompile = false;

                foreach ($files as $name => $file) {
                if (!is_file($output)||filemtime($output) < filemtime($file)) {
                    $need_recompile = true;
                    break;
                }
            }

            if ($need_recompile) {
                $tmp = '';
                foreach ($files as $file) {
                    $tmp .=  file_get_contents($file)."\n";
                }

                if($is_css) {
                    $tmp = $this->_process_css($tmp);
                }

                $gz_output = gzencode($tmp, 6);

                file_put_contents($output, $tmp);
                file_put_contents($output.'.gz', $gz_output);
            }

            if($is_css) {
                $this->_css[] = '<link type="text/css" href="' . $web_output . '?' . filemtime($output) . '" rel="stylesheet">';

            } else {
                $this->_js[Blocks::END][] = '<script src="'.$web_output . '?' . filemtime($output).'"></script>';
            }

            return;
        }

        $out = [];

        foreach ($files as $name => $file) {

            $output = path('pub').'/'.$this->assets_pub_dir . '/' . $name;

            if (!is_file($output)||filemtime($output) < filemtime($file)) {

                try {
                    copy($file, $output);
                } catch (\Exception $e) {
                    Mii::error('Cant copy file '.$output, 'mii');
                    $dir = dirname($output);
                    mkdir($dir, 0777, true);
                    copy($file['path'], $output);
                }
            }
            if($is_css) {
                $this->_css[] = '<link type="text/css" href="' . $this->assets_pub_dir . '/' . $name . '?' . filemtime($output) . '" rel="stylesheet">';
            } else {
                $this->_js[Blocks::END][] = '<script src="'.$this->assets_pub_dir . '/' . $name . '?' . filemtime($output).'"></script>';
            }
        }

        return implode("\n", $out);
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


    private function _build_assets_dir($blockname, $path)
    {

        $output = $this->assets_dir.'/'. $blockname ;

        if($this->use_symlink) {
            if(!is_link($output)) {
                symlink($path, $output);
            }
        } else {
            $this->_copy_dir($path, $output);
        }

    }

    // todo:
    private function _copy_dir($from, $to) {

        $dir = opendir($from);
        @mkdir($to);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file !== '.' ) && ( $file !== '..' )) {
                if ( is_dir($from . '/' . $file) ) {
                    $this->_copy_dir($from . '/' . $file, $to . '/' . $file);
                }
                else {

                    if (static::is_modified_later($to . '/' . $file, filemtime($from . '/' . $file))) {
                        copy($from . '/' . $file, $to . '/' . $file);
                    }
                }
            }
        }
        closedir($dir);
    }


}

