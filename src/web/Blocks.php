<?php

namespace mii\web;

use Mii;
use mii\core\Component;
use mii\core\ErrorException;
use mii\util\HTML;

class Blocks extends Component
{
    const HEAD = 0;
    const BEGIN = 1;
    const END = 2;

    public $base_path;

    public $use_static = false;

    protected $base_url = '/assets';

    protected $block_class = 'mii\web\Block';

    protected $assets_map_path = '@tmp';

    protected $current_set;

    protected $sets = [];

    protected $_blocks = [];

    protected $_used_blocks = [];
    protected $_used_files = [];

    protected $libraries;

    protected $merge = true;

    protected $use_symlink = true;

    protected $_rendered = false;

    protected $_css = [];

    protected $_js = [[], [], []];

    protected $_files = [
        'css' => [
        ],
        'js' => [
        ],
    ];

    protected $assets;


    public function init(array $config = []): void {
        parent::init($config);

        if (empty($this->sets)) {
            $this->sets[] = [
                'libraries' => [
                    '@app/blocks'
                ],
                'base_url' => '/assets'
            ];
        }

        $this->load_set(key($this->sets));
    }


    public function load_set(string $setname): void {

        $this->current_set = $setname;

        $default_set = [
            'libraries' => [],
            'base_url' => $this->base_url,
            'base_path' => null
        ];

        if (isset($this->sets[$setname])) {
            $set = $this->sets[$setname];
        } else {
            throw new ErrorException("Unknow blocks set name: $setname");
        }

        $set = array_replace($default_set, $set);

        foreach ($set as $key => $value)
            $this->$key = $value;

        for ($i = 0; $i < count($this->libraries); $i++)
            $this->libraries[$i] = Mii::resolve($this->libraries[$i]);
    }

    /**
     * Create a new block, or get an existing block
     * @static
     * @param $name string Block name
     * @return Block
     */
    public function get(string $name): Block {
        if (isset($this->_blocks[$name]))
            return $this->_blocks[$name];

        $this->_blocks[$name] = new $this->block_class($name);

        return $this->_blocks[$name];
    }


    public function get_block_php_file(string $name): ?string {

        $block_file = null;
        $block_path = $this->get_block_path($name);

        if (strpos($name, 'i_') !== 0) {

            $block_path .= $name;

            foreach ($this->libraries as $library_path) {
                if (is_file($library_path . $block_path . '.php')) {
                    return $library_path . $block_path . '.php';
                }
            }
        }
        return null;
    }

    public function get_block_path(string $name): ?string {
        return '/' . implode('/', explode('_', $name)) . '/';
    }


    public function css(): string {
        if (!$this->_rendered)
            $this->render();

        return implode("\n", $this->_css);
    }


    public function js(int $position = null): string {
        if (!$this->_rendered)
            $this->render();

        if ($position === null) {
            $out = [];
            foreach ($this->_js as $js) {
                if (!empty($js))
                    $out[] = implode("\n", $js);
            }
            return implode("\n", $out);
        }

        return implode("\n", $this->_js[$position]);
    }


    public function render(): void {

        if ($this->use_static) {
            $this->static_render();
            return;
        }

        if ($this->base_path === null) {
            $this->base_path = '@pub' . $this->base_url;
        }
        $this->base_path = Mii::resolve($this->base_path);

        if (!is_dir($this->base_path))
            mkdir($this->base_path, 0777, true);

        foreach ($this->_blocks as $block_name => $block) {
            if ($block->__has_parent)
                continue;

            $this->process_block_assets($block_name, $block_name, $block->_depends);
        }

        foreach ($this->_files as $type => $blocks) {
            foreach ($blocks as $block_name => $block) {

                if (isset($block['files']))
                    $this->_build_block($block_name, $type, $block['files']);

                if (isset($block['remote'])) {

                    if ($type === 'js') {
                        foreach ($block['remote'] as $position => $remote) {
                            $this->_js[$position][] = implode("\n", $remote);
                        }
                    } else {

                        foreach ($block['remote'] as $css_remote) {
                            $this->_css[] = '<link type="text/css" href="' . $css_remote . '" rel="stylesheet" />';
                        }
                    }

                }
                if (isset($block['inline'])) {

                    if ($type === 'js') {
                        foreach ($block['inline'] as $position => $inline) {
                            $this->_js[$position][] = '<script type="text/javascript">' . implode("\n", $inline) . '</script>';
                        }

                    } else {
                        $content = implode("\n", $block['inline']);
                        $this->_css[] = '<style>' . $content . '</style>';
                    }
                }

            }
        }

        $this->_rendered = true;
    }

    /**
     * Recursively process a block and its dependencies
     *
     * @param $block_name
     */
    public function process_block_assets($block_name, $parent_block, $depends): void {

        if (isset($this->_used_blocks[$block_name]))
            return;

        $block_path = $this->get_block_path($block_name);

        foreach ($this->libraries as $base_path) {

            if (is_dir($base_path . $block_path . 'assets')) {
                $this->_build_assets_dir($block_name, $base_path . $block_path . 'assets');
                break;
            }
        }

        if (!empty($depends)) {
            foreach ($depends as $depend) {
                if (isset($this->_used_blocks[$depend])) {
                    continue;
                }
                $this->process_block_assets($depend, $parent_block, $this->_blocks[$depend]->_depends);
                $this->_used_blocks[$depend] = true;
            }
        }
        $types = ['css', 'js'];

        foreach ($types as $type) {
            foreach ($this->libraries as $base_path) {

                if (is_file($base_path . $block_path . $block_name . '.' . $type)) {
                    $this->_files[$type][$parent_block]['files'][$block_name . '.' . $type] = $base_path . $block_path . $block_name . '.' . $type;
                    break;
                }
            }
        }

        if ($this->_blocks[$block_name]->__remote_js !== null) {
            foreach ($this->_blocks[$block_name]->__remote_js as $link => $settings) {
                if (!empty($settings) AND isset($settings['position'])) {
                    $position = $settings['position'];
                    unset($settings['position']);
                } else {
                    $position = Blocks::END;;
                }
                $this->_files['js'][$parent_block]['remote'][$position][] = HTML::script($link, $settings);
            }
        }

        if ($this->_blocks[$block_name]->__remote_css !== null) {
            if (!isset($this->_files['css'][$parent_block]['remote']))
                $this->_files['css'][$parent_block]['remote'] = [];

            foreach ($this->_blocks[$block_name]->__remote_css as $link) {
                $this->_files['css'][$parent_block]['remote'][] = $link;
            }
        }

        if (!empty($this->_blocks[$block_name]->__inline_js)) {

            foreach ($this->_blocks[$block_name]->__inline_js as $inline) {
                $position = (!empty($inline[1]) AND isset($inline[1]['position'])) ? $inline[1]['position'] : Blocks::END;
                if (!isset($this->_files['js'][$parent_block]['inline'][$position]))
                    $this->_files['js'][$parent_block]['inline'][$position] = [];
                $this->_files['js'][$parent_block]['inline'][$position][] = $inline[0];
            }
        }

        if (!empty($this->_blocks[$block_name]->__inline_css)) {
            if (!isset($this->_files['css'][$parent_block]['inline']))
                $this->_files['css'][$parent_block]['inline'] = $this->_blocks[$block_name]->__inline_css;
            else
                $this->_files['css'][$parent_block]['inline'] = array_merge($this->_files['.css'][$parent_block]['inline'], $this->_blocks[$block_name]->__inline_css);
        }
    }

    public function assets_path_by_name($block_name) {
        $block_path = $this->get_block_path($block_name);

        foreach ($this->libraries as $base_path) {

            if (is_dir($base_path . $block_path . 'assets')) {
                return $base_path . $block_path . 'assets';
            }
        }
        return false;
    }


    private function _build_block(string $block_name, string $type, array $files): void {

        $result_file_name = $block_name . '.' . substr(md5(implode('', array_keys($files))), 0, 16);

        if ($this->merge) {
            $web_output = $this->base_url . '/' . $result_file_name . '.' . $type;
            $output = $this->base_path . '/' . $result_file_name . '.' . $type;

            $need_recompile = false;

            foreach ($files as $name => $file) {
                if (!is_file($output) || filemtime($output) < filemtime($file)) {
                    $need_recompile = true;
                    break;
                }
            }

            if ($need_recompile) {
                $tmp = '';
                foreach ($files as $file) {
                    $tmp .= file_get_contents($file) . "\n";
                }

                file_put_contents($output, $tmp);
            }

            if ($type === 'css') {
                $this->_css[] = '<link type="text/css" href="' . $web_output . '?' . filemtime($output) . '" rel="stylesheet">';

            } else {
                $this->_js[Blocks::END][] = '<script src="' . $web_output . '?' . filemtime($output) . '"></script>';
            }

            return;
        }

        foreach ($files as $name => $file) {

            $output = $this->base_path . '/' . $name;

            if (!is_file($output) || filemtime($output) < filemtime($file)) {

                try {
                    copy($file, $output);
                } catch (\Exception $e) {
                    $dir = dirname($output);
                    mkdir($dir, 0777, true);
                    copy($file['path'], $output);
                }
            }
            if ($type === 'css') {
                $this->_css[] = '<link type="text/css" href="' . $this->base_url . '/' . $name . '?' . filemtime($output) . '" rel="stylesheet">';
            } else {
                $this->_js[Blocks::END][] = '<script src="' . $this->base_url . '/' . $name . '?' . filemtime($output) . '"></script>';
            }
        }
    }


    private function _build_assets_dir(string $blockname, string $path): void {

        $output = $this->base_path . '/' . $blockname;

        if (true || $this->use_symlink) {
            if (!is_link($output)) {
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
        while (false !== ($file = readdir($dir))) {
            if (($file !== '.') && ($file !== '..')) {
                if (is_dir($from . '/' . $file)) {
                    $this->_copy_dir($from . '/' . $file, $to . '/' . $file);
                } else {

                    if (static::is_modified_later($to . '/' . $file, filemtime($from . '/' . $file))) {
                        copy($from . '/' . $file, $to . '/' . $file);
                    }
                }
            }
        }
        closedir($dir);
    }


    private function static_render() {

        $this->assets_map_path = Mii::resolve($this->assets_map_path);
        $this->assets = require($this->assets_map_path . "/{$this->current_set}.assets");

        foreach ($this->_blocks as $block_name => $block) {
            if ($block->__has_parent)
                continue;
            $this->static_process_block_assets($block_name, $block_name, $block->_depends);
        }

        $this->_rendered = true;
    }

    /**
     * Recursively process a block and its dependencies
     *
     * @param $block_name
     */
    public function static_process_block_assets($block_name, $parent_block, $depends): void {
        if (isset($this->_used_blocks[$block_name])) {
            return;
        }

        if (!empty($depends)) {
            foreach ($depends as $depend) {
                $this->static_process_block_assets($depend, $parent_block, $this->_blocks[$depend]->_depends);
                $this->_used_blocks[$depend] = true;
            }
        }

        if (isset($this->assets['css'][$block_name])) {
            $filename = $this->assets['css'][$block_name];
            if (!isset($this->_used_files['css'][$filename])) {
                $this->_css[] = '<link type="text/css" href="' . $this->base_url . '/' . $filename . '.css" rel="stylesheet">';
                $this->_used_files['css'][$filename] = true;
            }
        }

        if (isset($this->assets['js'][$block_name])) {
            $filename = $this->assets['js'][$block_name];
            if (!isset($this->_used_files['js'][$filename])) {
                $this->_js[Blocks::END][] = '<script src="' . $this->base_url . '/' . $filename . '.js"></script>';
                $this->_used_files['js'][$filename] = true;
            }
        }

        if ($this->_blocks[$block_name]->__remote_js !== null) {
            foreach ($this->_blocks[$block_name]->__remote_js as $link => $settings) {
                if (!empty($settings) AND isset($settings['position'])) {
                    $position = $settings['position'];
                    unset($settings['position']);
                } else {
                    $position = Blocks::END;
                }
                $this->_js[$position][] = HTML::script($link, $settings);
            }
        }

        if ($this->_blocks[$block_name]->__remote_css !== null) {
            foreach ($this->_blocks[$block_name]->__remote_css as $link) {
                $this->_css[] = '<link type="text/css" href="' . $link . '" rel="stylesheet" />';
            }
        }

        if (!empty($this->_blocks[$block_name]->__inline_js)) {

            foreach ($this->_blocks[$block_name]->__inline_js as $inline) {
                $position = (!empty($inline[1]) AND isset($inline[1]['position'])) ? $inline[1]['position'] : Blocks::END;
                $this->_js[$position][] = '<script type="text/javascript">' . $inline[0] . '</script>';
            }
        }

        if (!empty($this->_blocks[$block_name]->__inline_css)) {
            foreach ($this->_blocks[$block_name]->__inline_css as $style) {
                $this->_css[] = HTML::tag('style', $style[0], $style[1]);
            }
        }
    }
}

