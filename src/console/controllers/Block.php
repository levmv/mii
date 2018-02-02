<?php

namespace mii\console\controllers;


use Mii;
use mii\console\CliException;
use mii\console\Controller;

class Block extends Controller
{
    public $description = 'Blocks builder';

    protected $input_path;

    protected $output_path;

    protected $blocks = [];

    protected $check_mtime = false;

    public function before() {

        $list = config('console.block.rules', []);


        if (empty($list)) {
            // Old way: list of blocks returned by method block_rules();
            $this->warning('Warning: block_rules() method is deprecated. Please, use config(console.block.rules)');
            $this->blocks = $this->block_rules();
        } else {
            // New way - get from the config

            foreach ($list as $namespace => $blocks) {
                if (!isset($this->blocks[$namespace]))
                    $this->blocks[$namespace] = [];

                foreach ($blocks as $index => $value) {
                    // Block can be defined either by simply name or by name => func_name

                    if (is_int($index)) {
                        $index = $value;

                        // Drop 'i_' prefix.
                        if (strpos($value, 'i_') === 0) {
                            $name = substr($index, 2);
                        }
                        $value = 'do_' . str_replace('-', '_', $name);
                    }

                    $this->blocks[$namespace][$index] = $value;
                }
            }
        }

        $this->input_path = Mii::resolve(config('console.block.input_path', '@root/node_modules/'));
    }

    /**
     * @deprecated
     * @return array
     */
    public function block_rules() {
        return [
            path('app') . '/blocks' => [
                'i_jquery' => 'do_jquery',
                'i_fancybox' => 'do_fancybox'
            ],
        ];
    }


    public function index($argv) {
        $this->check_mtime = isset($argv['check_mtime']) ? true : false;

        foreach ($this->blocks as $output_path => $blocks) {

            $this->info("\n# Processing $output_path");

            foreach ($blocks as $block => $func) {

                $this->output_path = Mii::resolve($output_path);

                if (!method_exists($this, $func)) {
                    $this->error("Method $func doesnt exist");
                } else {
                    try {
                        $this->{$func}($block);
                        $this->info(':block compiled.', [':block' => $block]);
                    } catch (CliException $e) {
                        $this->error($e->getMessage());
                    }
                }
            }
        }
    }


    protected function do_jquery($block) {
        $this->to_block('jquery/dist/jquery.min.js', $block, 'js');
    }


    protected function do_m1k($block) {

        $this->to_block([
            'jquery-m1k/jquery.m1k.min.js'
        ], $block, 'js');

        $this->to_block([
            'jquery-m1k/jquery.m1k.min.css'
        ], $block, 'css');
    }


    protected function do_jcrop($block) {
        $this->to_block('Jcrop/js/Jcrop.min.js', $block, 'js');
        $this->to_block('Jcrop/css/Jcrop.min.css', $block, 'css', function ($text) use ($block) {
            return str_replace('url(', 'url(/assets/' . $block . '/', $text);
        });
        $this->to_assets('Jcrop/css/Jcrop.gif', $block);
    }


    protected function do_timeago($block) {
        $this->to_block('timeago/jquery.timeago.js', $block, 'js', function ($text) {
            return $text . file_get_contents($this->input_path . '/timeago/locales/jquery.timeago.ru.js');
        });
    }

    protected function do_dot($block) {
        $this->to_block('doT/doT.min.js', $block, 'js');
    }


    protected function do_blueimp($block) {
        $this->to_block([
            'blueimp-file-upload/js/vendor/jquery.ui.widget.js',
            'blueimp-file-upload/js/jquery.iframe-transport.js',
            'blueimp-file-upload/js/jquery.fileupload.js',
        ], $block, 'js');

        $this->to_block([
            'blueimp-file-upload/css/jquery.fileupload.css'
        ], $block, 'css');
    }


    protected function do_tinymce($block) {
        $this->to_block(
            [
                'tinymce/tinymce.min.js',
                'tinymce/themes/modern/theme.min.js',

                'tinymce/plugins/autoresize/plugin.min.js',
                'tinymce/plugins/link/plugin.min.js',
                'tinymce/plugins/code/plugin.min.js',
                'tinymce/plugins/image/plugin.min.js',
                'tinymce/plugins/wordcount/plugin.min.js',
                'tinymce/plugins/media/plugin.min.js',
                'tinymce/plugins/paste/plugin.min.js',
                'tinymce/plugins/table/plugin.min.js',
                'tinymce/plugins/hr/plugin.min.js',
                'tinymce/plugins/lists/plugin.min.js'
            ],
            $block,
            'js'
        );

        $this->to_assets(
            [
                'tinymce/skins/lightgray/skin.min.css',
                'tinymce/skins/lightgray/content.min.css'
            ],
            $block,
            function ($text) use ($block) {
                return str_replace('fonts', '/assets/a/' . $block, $text);
            }
        );

        $this->to_assets([
            'tinymce-i18n/langs/ru.js',
            'tinymce/skins/lightgray/fonts/tinymce.woff',
            'tinymce/skins/lightgray/fonts/tinymce.ttf'
        ], $block);
    }

    /**
     * @param $block
     * @throws CliException
     */
    protected function do_chosen($block) {

        $this->to_block('chosen/chosen.jquery.min.js', $block, 'js');

        $this->to_block('chosen/chosen.min.css', $block, 'css', function ($text) use ($block) {
            return str_replace('url(chosen', 'url(/assets/' . $block . '/chosen', $text);
        });

        $this->to_assets('chosen/chosen-sprite.png', $block);
        $this->to_assets('chosen/chosen-sprite@2x.png', $block);
    }


    protected function do_select2($block) {
        $this->to_block('select2/dist/js/select2.full.min.js', $block, 'js');
        $this->to_block('select2/dist/js/i18n/ru.js', $block . '_ru', 'js');

        $this->to_block('select2/dist/css/select2.min.css', $block, 'css');
    }


    protected function do_fotorama($block) {

        $this->to_block('fotorama/fotorama.js', $block, 'js');

        $this->to_block('fotorama/fotorama.css', $block, 'css', function ($text) use ($block) {
            return str_replace('url(fotorama', 'url(/assets/' . $block . '/fotorama', $text);
        });

        $this->to_assets('fotorama/fotorama.png', $block);
        $this->to_assets('fotorama/fotorama@2x.png', $block);
    }


    protected function do_magnific($block) {
        $this->to_block('magnific-popup/dist/jquery.magnific-popup.min.js', $block, 'js');
        $this->to_block('magnific-popup/dist/magnific-popup.css', $block, 'css');
    }


    protected function do_fancybox($block) {

        $this->to_block('fancyBox/source/jquery.fancybox.pack.js', $block, 'js');

        $this->to_block('fancyBox/source/jquery.fancybox.css', 'i_fancybox', 'css', function ($text) use ($block) {
            return str_replace("url('", "url('/assets/" . $block . "/", $text);
        });

        $this->to_assets('fancyBox/source/fancybox_loading.gif', $block);
        $this->to_assets('fancyBox/source/fancybox_loading@2x.gif', $block);

        $this->to_assets('fancyBox/source/blank.gif', $block);
        $this->to_assets('fancyBox/source/fancybox_overlay.png', $block);
        $this->to_assets('fancyBox/source/fancybox_sprite.png', $block);
        $this->to_assets('fancyBox/source/fancybox_sprite@2x.png', $block);

        //$this->to_assets('fancybox/source/fotorama@2x.png', $block);
    }


    protected function do_plupload($block) {
        $this->to_block('plupload/js/plupload.full.min.js', $block, 'js');
        $this->to_assets('plupload/js/Moxie.swf', $block);
        $this->to_assets('plupload/js/Moxie.xap', $block);

    }

    protected function do_jquery_ui($block) {
        $this->to_block([
            'jquery-ui/ui/data.js',
            'jquery-ui/ui/scroll-parent.js',
            'jquery-ui/ui/widget.js',
            'jquery-ui/ui/widgets/mouse.js',
            'jquery-ui/ui/widgets/sortable.js',
            'jquery-ui/ui/widgets/datepicker.js'
        ], $block, 'js');

        $this->to_block([
            'jquery-ui/themes/base/datepicker.css',
            'jquery-ui/themes/base/sortable.css',
            'jquery-ui/themes/base/theme.css'
        ], $block, 'css',
            function ($text) use ($block) {
                return str_replace('url("images', 'url("/assets/a/' . $block, $text);
            });

        $this->iterate_dir('jquery-ui/themes/base/images', function ($file) use ($block) {
            $this->to_assets('jquery-ui/themes/base/images/' . $file, $block);
        });
    }

    protected function do_spinjs($block) {
        $this->to_block('spin.js/spin.min.js', $block, 'js');
    }


    protected function do_fontawesome($block) {
        $this->to_block('font-awesome/css/font-awesome.min.css', $block, 'css',
            function ($text) use ($block) {
                return str_replace('../fonts', '/assets/' . $block, $text);
            });
        $this->iterate_dir('font-awesome/fonts/', function ($file) use ($block) {
            $this->to_assets('font-awesome/fonts/' . $file, $block);
        });
    }

    /**
     * @param $from
     * @param $block_name
     * @param $ext
     * @param null $callback
     * @throws CliException
     */
    protected function to_block($from, $block_name, $ext, $callback = null) {
        if (!is_array($from))
            $from = array($from);

        $dir = $this->output_path . '/' . implode('/', explode('_', $block_name));

        if (!is_dir($dir)) {
            try {
                mkdir($dir, 0777, true);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $this->error($dir);
            }
        }

        $to = $dir . '/' . $block_name . '.' . $ext;
        $exist = file_exists($to);

        $out = '';
        $same = true;

        foreach ($from as $f) {

            if (!file_exists($this->input_path . '/' . $f))
                throw new CliException('Source for :block not found. Skip.', [':block' => $block_name]);

            if (!$exist OR filemtime($this->input_path . '/' . $f) > filemtime($to))
                $same = false;
        }

        if ($same AND $this->check_mtime) {
            return;
        }

        foreach ($from as $f) {
            $text = file_get_contents($this->input_path . '/' . $f);

            if ($callback)
                $text = call_user_func($callback, $text);

            $out .= $text . "\n";
        }

        file_put_contents($to, $out);
    }

    protected function to_assets($from, $block_name, $callback = null) {
        if (!is_array($from))
            $from = array($from);

        $dir = $this->output_path . '/' . implode('/', explode('_', $block_name)) . '/assets';

        if (!is_dir($dir)) {
            mkdir($dir);
        }

        foreach ($from as $f) {

            $filename = basename($f, PATHINFO_FILENAME);

            if ($callback) {
                $file = file_get_contents($this->input_path . '/' . $f);
                file_put_contents($dir . '/' . $filename, call_user_func($callback, $file));
            } else {
                copy($this->input_path . '/' . $f, $dir . '/' . $filename);
            }
        }
    }

    protected function iterate_dir($from, $callback) {
        $files = scandir($this->input_path . '/' . $from);
        array_map(function ($item) use ($callback) {
            if ($item === '.' OR $item === '..')
                return;

            call_user_func($callback, $item);
        }, $files);
    }
}