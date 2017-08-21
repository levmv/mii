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

    protected $static_source = 'static';

    protected $static = [];

    protected $sets = [];

    protected $_blocks = [];

    protected $_block_paths = [];

    protected $_used_blocks = [];
    protected $_used_files = [];

    protected $libraries;

    protected $merge = true;

    protected $process_assets = true;

    protected $use_symlink = true;

    protected $css_process_callback;

    protected $_rendered = false;

    protected $_css = [];

    protected $_js = [[], [], []];

    protected $_files = [
        'css' => [
        ],
        'js' => [
        ],
    ];


    protected $blocks = [];
    protected $_reverse = [];


    public function init(array $config = []): void {
        parent::init($config);

        $this->libraries = [
            path('app') . '/blocks'
        ];

        $this->load_set('default');
    }

    public function load_set($set): void {

        $default_set = [
            'libraries' => $this->libraries,
            'base_url' => $this->base_url,
            'base_path' => null
        ];

        if (!is_array($set)) {
            if (!isset($this->sets[$set]) && $set === 'default') {
                $set = [];
                $default_set['base_path'] = $this->base_path;
            } elseif (isset($this->sets[$set])) {
                $set = $this->sets[$set];
            } else {
                throw new ErrorException("Unknow blocks set name: $set");
            }
        }

        $set = array_replace_recursive($default_set, $set);

        foreach ($set as $key => $value)
            $this->$key = $value;


        for ($i = 0; $i < count($this->libraries); $i++)
            $this->libraries[$i] = Mii::resolve($this->libraries[$i]);

        $this->base_url = Mii::resolve($this->base_url);

        if ($this->base_path === null) {
            $this->base_path = path('pub') . $this->base_url;
        } else {
            $this->base_path = Mii::resolve($this->base_path);
        }

        if (!$this->use_static) {
            if (!is_dir($this->base_path))
                mkdir($this->base_path, 0777, true);
        }

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


    public function js(?int $position = null): string {
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

        foreach ($this->_blocks as $block_name => $block) {

            if ($block->__has_parent)
                continue;
            $this->process_block_assets($block_name, $block_name, $block->_depends);
        }


        foreach ($this->_files as $type => $blocks) {
            foreach ($blocks as $block_name => $block) {

                if (isset($block['files']))
                    if ($this->use_static) {
                        foreach ($block['files'] as $file => $crap) {

                            $web_output = $this->base_url . '/' . $this->revision . '/' . $file . '.' . $type;

                            if ($type === 'css') {
                                $this->_css[] = '<link type="text/css" href="' . $web_output . '" rel="stylesheet">';
                            } else {
                                $this->_js[Blocks::END][] = '<script src="' . $web_output . '"></script>';
                            }

                        }
                    } else {

                        $this->_build_block($block_name, $type, $block['files']);

                    }

                if (isset($block['remote'])) {

                    if ($type === 'js') {
                        foreach ($block['remote'] as $position => $remote) {
                            $this->_js[$position][] = implode("\n", $remote);
                        }
                    } else {

                        foreach ($block['remote'] as $condition => $css_remote) {
                            if ($condition) {
                                $this->_css[] = '<!--[if ' . $condition . ']><link type="text/css" href="' . implode("\n", $css_remote) . '" rel="stylesheet" /><![endif]-->';
                            } else {
                                $this->_css[] = '<link type="text/css" href="' . implode("\n", $css_remote) . '" rel="stylesheet" />';
                            }
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
                        $this->_css[] = '<link type="text/css" href="' . $content . '" rel="stylesheet" />';
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

        if ( $this->process_assets) {
            foreach ($this->libraries as $base_path) {
                if (is_dir($base_path . $block_path . 'assets')) {
                    $this->_build_assets_dir($block_name, $base_path . $block_path . 'assets');
                }
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
                if (isset($settings['condition'])) {
                    $condition = $settings['condition'];
                    unset($settings['condition']);
                    $this->_files['js'][$parent_block]['remote'][$position][] = '<!--[if ' . $condition . ']>' . HTML::script($link, $settings) . '<![endif]-->';
                } else {
                    $this->_files['js'][$parent_block]['remote'][$position][] = HTML::script($link, $settings);
                }
            }
        }

        if ($this->_blocks[$block_name]->__remote_css !== null) {
            if (!isset($this->_files['css'][$parent_block]['remote']))
                $this->_files['css'][$parent_block]['remote'] = [];

            foreach ($this->_blocks[$block_name]->__remote_css as $r_css => $r_options) {
                $condition = isset($r_options['condition']) ? $r_options['condition'] : '';
                $this->_files['css'][$parent_block]['remote'][$condition][] = $r_css;
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


    private function _build_block(string $block_name, string $type, array $files): void {

        $result_file_name = $block_name . crc32(implode('', array_keys($files)));

        if (config('debug')) {
            $benchmark = \mii\util\Profiler::start('Assets', $result_file_name . '.' . $type);
        }

        $is_css = ($type === 'css');

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

                if ($is_css && $this->css_process_callback) {
                    try {
                        $tmp = call_user_func($this->css_process_callback, $tmp);
                    } catch (\Throwable $e) {
                        Mii::error('CSS user processing failed', 'mii');
                    }
                }

                $gz_output = gzencode($tmp, 6);

                file_put_contents($output, $tmp);
                file_put_contents($output . '.gz', $gz_output);
            }

            if ($is_css) {
                $this->_css[] = '<link type="text/css" href="' . $web_output . '?' . filemtime($output) . '" rel="stylesheet">';

            } else {
                $this->_js[Blocks::END][] = '<script src="' . $web_output . '?' . filemtime($output) . '"></script>';
            }

            if (config('debug')) {
                \mii\util\Profiler::stop($benchmark);
                if (empty($this->_css) AND empty($this->_js[0]) AND empty($this->_js[1]) AND empty($this->_js[2]))
                    \mii\util\Profiler::delete($benchmark);
            }

            return;
        }

        foreach ($files as $name => $file) {

            $output = $this->base_path . '/' . $name;

            if (!is_file($output) || filemtime($output) < filemtime($file)) {

                try {
                    copy($file, $output);
                } catch (\Exception $e) {
                    Mii::error("Cant copy file $output", 'mii');
                    $dir = dirname($output);
                    mkdir($dir, 0777, true);
                    copy($file['path'], $output);
                }
            }
            if ($is_css) {
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

        foreach ($this->{$this->static_source} as $name => $block) {
            foreach (['css', 'js'] as $type) {
                if (isset($block[$type])) {
                    if (!is_array($block[$type]))
                        $block[$type] = (array)$block[$type];

                    foreach ($block[$type] as $child_block) {
                        if (!isset($this->_reverse[$type][$child_block]))
                            $this->_reverse[$type][$child_block] = $name;
                    }
                }
            }
        }

        foreach ($this->_blocks as $block_name => $block) {

            if ($block->__has_parent)
                continue;
            $this->static_process_block_assets($block_name, $block_name, $block->_depends);
        }


        foreach ($this->_files as $type => $includes) {
            foreach ($includes as $include) {

                if (isset($include['files']))

                    foreach ($include['files'] as $file) {

                        $web_output = $this->base_url . '/' . $this->revision . '/' . $file . '.' . $type;

                        if ($type === 'css') {
                            $this->_css[] = '<link type="text/css" href="' . $web_output . '" rel="stylesheet">';
                        } else {
                            $this->_js[Blocks::END][] = '<script src="' . $web_output . '"></script>';
                        }
                    }

                if (isset($include['remote'])) {

                    if ($type === 'js') {
                        foreach ($include['remote'] as $position => $remote) {
                            $this->_js[$position][] = implode("\n", $remote);
                        }
                    } else {

                        foreach ($include['remote'] as $condition => $css_remote) {
                            if ($condition) {
                                $this->_css[] = '<!--[if ' . $condition . ']><link type="text/css" href="' . implode("\n", $css_remote) . '" rel="stylesheet" /><![endif]-->';
                            } else {
                                $this->_css[] = '<link type="text/css" href="' . implode("\n", $css_remote) . '" rel="stylesheet" />';
                            }
                        }
                    }

                }
                if (isset($include['inline'])) {

                    if ($type === 'js') {
                        foreach ($include['inline'] as $position => $inline) {
                            $this->_js[$position][] = '<script type="text/javascript">' . implode("\n", $inline) . '</script>';
                        }

                    } else {
                        $content = implode("\n", $include['inline']);
                        $this->_css[] = '<link type="text/css" href="' . $content . '" rel="stylesheet" />';
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
    public function static_process_block_assets($block_name, $parent_block, $depends): void {
        if (isset($this->_used_blocks[$block_name])) {
            return;
        }

        $block_path = $this->get_block_path($block_name);

        if (!$this->use_static && $this->process_assets) {
            foreach ($this->libraries as $base_path) {
                if (is_dir($base_path . $block_path . 'assets')) {
                    $this->_build_assets_dir($block_name, $base_path . $block_path . 'assets');
                }
                break;
            }
        }

        if (!empty($depends)) {
            foreach ($depends as $depend) {
                $this->static_process_block_assets($depend, $parent_block, $this->_blocks[$depend]->_depends);
                $this->_used_blocks[$depend] = true;
            }
        }

        $include = [
            'css' => [],
            'js' => []
        ];

        foreach (['css', 'js'] as $type) {
            if (isset($this->_reverse[$type][$block_name]) && !$this->_used_files[$type][$this->_reverse[$type][$block_name]]) {
                $name = $this->_reverse[$type][$block_name];
                $include[$type]['files'][] = $name;

                $this->_used_files[$type][$name] = true;
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
                if (isset($settings['condition'])) {
                    $condition = $settings['condition'];
                    unset($settings['condition']);
                    $include['js']['remote'][$position][] = '<!--[if ' . $condition . ']>' . HTML::script($link, $settings) . '<![endif]-->';
                } else {
                    $include['js']['remote'][$position][] = HTML::script($link, $settings);
                }
            }
        }

        if ($this->_blocks[$block_name]->__remote_css !== null) {
            if (!isset($this->_files['css'][$parent_block]['remote']))
                $include['css']['remote'] = [];

            foreach ($this->_blocks[$block_name]->__remote_css as $r_css => $r_options) {
                $condition = isset($r_options['condition']) ? $r_options['condition'] : '';
                $include['css']['remote'][$condition][] = $r_css;
            }
        }

        if (!empty($this->_blocks[$block_name]->__inline_js)) {

            foreach ($this->_blocks[$block_name]->__inline_js as $inline) {
                $position = (!empty($inline[1]) AND isset($inline[1]['position'])) ? $inline[1]['position'] : Blocks::END;
                if (!isset($this->_files['js'][$parent_block]['inline'][$position]))
                    $include['js']['inline'][$position] = [];
                $include['js']['inline'][$position][] = $inline[0];
            }
        }

        if (!empty($this->_blocks[$block_name]->__inline_css)) {
            if (!isset($this->_files['css'][$parent_block]['inline']))
                $include['css']['inline'] = $this->_blocks[$block_name]->__inline_css;
            else
                $include['css']['inline'] = array_merge($this->_files['.css'][$parent_block]['inline'], $this->_blocks[$block_name]->__inline_css);
        }

        if (!empty($include['css']))
            $this->_files['css'][] = $include['css'];

        if (!empty($include['js']))
            $this->_files['js'][] = $include['js'];
    }
}

