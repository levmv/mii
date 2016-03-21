<?php

namespace mii\console\controllers;


use mii\console\CliException;
use mii\console\Controller;
use mii\core\Exception;
use mii\db\DB;

class Block extends Controller {

    public $description = 'Blocks builder';

    protected $input_path;

    protected $output_path;

    public function before() {

        $this->input_path = path('vendor').'/bower/';
        $this->output_path = path('app').'/blocks';

        $this->blocks = $this->block_rules();

    }

    public function block_rules() {
        return [
            path('app').'/blocks' => [
                'i_jquery' => 'do_jquery',
                'i_chosen' => 'do_chosen',
                'i_fancybox' => 'do_fancybox'
            ],
        ];
    }


    public function index($argv) {

        foreach($this->blocks as $output_path => $blocks) {
            foreach($blocks as $block => $func) {

                $this->output_path = $output_path;

                try {
                    $this->{$func}($block);
                    $this->info(':block compiled.', [':block' => $block]);
                } catch (CliException $e) {
                    $this->error($e->getMessage());
                }
            }

        }

    }


    protected function do_jquery($block) {
        $this->to_block('jquery/dist/jquery.min.js', $block, 'js');
    }

    protected function do_chosen($block) {

        $this->to_block('chosen/chosen.jquery.min.js', $block, 'js');

        $this->to_block('chosen/chosen.min.css', $block, 'css', function($text) use ($block) {
            return str_replace('url(chosen', 'url(/assets/'.$block.'/chosen', $text);
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

        $this->to_block('fotorama/fotorama.css', $block, 'css', function($text) use ($block) {
            return str_replace('url(fotorama', 'url(/assets/'.$block.'/fotorama', $text);
        });

        $this->to_assets('fotorama/fotorama.png', $block);
        $this->to_assets('fotorama/fotorama@2x.png', $block);
    }


    protected function do_fancybox($block) {

        $this->to_block('fancyBox/source/jquery.fancybox.pack.js', $block, 'js');

        $this->to_block('fancyBox/source/jquery.fancybox.css', 'i_fancybox', 'css', function($text) use ($block) {
            return str_replace("url('", "url('/assets/".$block."/", $text);
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


    protected function do_jqueryui($block) {
        $this->to_block('jquery-ui/jquery-ui.min.js', $block, 'js');
        $this->to_block('jquery-ui/themes/ui-lightness/jquery-ui.min.css', $block, 'css',
            function($text) use ($block) {
                return str_replace('url("images', 'url("assets/'.$block, $text);
            });

        $this->iterate_dir('jquery-ui/themes/ui-lightness/images', function($file) use ($block) {
            $this->to_assets('jquery-ui/themes/ui-lightness/images/'.$file, $block);
        });

    }

    protected function do_spinjs($block) {
        $this->to_block('spin.js/spin.min.js', $block, 'js');
    }


    protected function do_fontawesome($block) {
        $this->to_block('font-awesome/css/font-awesome.min.css', $block, 'css',
            function($text) use ($block) {
                return str_replace('../fonts', '/assets/'.$block, $text);
            });
        $this->iterate_dir('font-awesome/fonts/', function($file) use ($block) {
            $this->to_assets('font-awesome/fonts/'.$file, $block);
        });
    }


    protected function to_block($from, $block_name, $ext, $callback = null) {

        if(! file_exists($this->input_path . '/'. $from))
            throw new CliException('Source for :block not found. Skip.', [':block' => $block_name]);

        $dir = $this->output_path.'/'.implode('/', explode('_', $block_name));
        if(!is_dir($dir)) {
            try {
                mkdir($dir, 0777, true);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $this->error($dir);
            }
        }

        if($callback) {
            $text = file_get_contents($this->input_path . '/'. $from);
            file_put_contents($dir.'/'.$block_name.'.'.$ext, call_user_func($callback, $text));

        } else {
            copy($this->input_path . '/'. $from, $dir.'/'.$block_name.'.'.$ext);
        }

    }

    protected function to_assets($from, $block_name) {

        $filename = basename($from, PATHINFO_FILENAME);

        $dir = $this->output_path.'/'.implode('/', explode('_', $block_name)).'/assets';
        if(!is_dir($dir)) {
            mkdir($dir);
        }

        copy($this->input_path . '/'. $from, $dir.'/'.$filename);
    }

    protected function iterate_dir($from, $callback) {
        $files = scandir($this->input_path . '/'. $from);
        array_map(function($item) use ($callback) {
            if($item === '.' OR $item === '..')
                return;

            call_user_func($callback, $item);
        }, $files);
    }
}