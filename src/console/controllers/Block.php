<?php declare(strict_types=1);

namespace mii\console\controllers;

use Mii;
use mii\console\Controller;
use mii\core\Exception;
use mii\util\Console;
use mii\util\FS;

/**
 * Blocks builder
 *
 * @package mii\console\controllers
 */
class Block extends Controller
{
    protected string $input_path;

    protected string $output_path;

    protected array $blocks = [];

    protected bool $force = false;

    private int $changedFiles = 0;

    protected function before()
    {
        $list = config('console.block.rules', []);

        if (empty($list)) {
            $this->warning('Warning: console.block.rules is empty');
        }

        foreach ($list as $namespace => $blocks) {
            if (!isset($this->blocks[$namespace])) {
                $this->blocks[$namespace] = [];
            }

            foreach ($blocks as $index => $value) {
                // Block can be defined either by simply name or by name => func_name

                if (\is_int($index)) {
                    $index = $value;

                    // Drop 'i_' prefix.
                    if (\str_starts_with($value, 'i_')) {
                        $name = \substr($index, 2);
                    } else {
                        $name = $index;
                    }
                    $name = \str_replace('-', '_', $name);
                    $parts = \explode('_', $name);
                    $parts = \array_map('ucfirst', $parts);
                    $value = 'do' . \implode($parts);
                }

                $this->blocks[$namespace][$index] = $value;
            }
        }

        $this->input_path = Mii::resolve((string)config('console.block.input_path', '@root/node_modules/'));
    }


    public function index($argv)
    {
        $this->force = $this->request->param('force', false);
        $totalChanged = 0;

        foreach ($this->blocks as $output_path => $blocks) {
            $this->info("\n# $output_path");

            foreach ($blocks as $block => $func) {
                $this->output_path = Mii::resolve($output_path);

                if (!\method_exists($this, $func)) {
                    $this->error("Method $func doesnt exist");
                } else {
                    try {
                        $this->{$func}($block);

                        if ($this->changedFiles > 0) {
                            $this->info("$block updated ($this->changedFiles)");
                        } else {
                            Console::stdout($block . "\n", Console::FG_GREY);
                        }

                        $totalChanged += $this->changedFiles;

                        $this->changedFiles = 0;
                    } catch (\Throwable $e) {
                        $this->error($e->getMessage());
                    }
                }
            }
        }

        $this->info("Changed files: $totalChanged");
    }

    protected function doCore($block)
    {
        $this->toBlock('core.default/dist/core.full.min.js', $block, 'js');
        $this->toBlock([
            'core.default/dist/core.css',
            'core.default/dist/modal.css',
            'core.default/dist/dropdown.css',
            'core.default/dist/dots.css',
        ], $block, 'css');
    }


    protected function doMiitable($block)
    {
        $this->toBlock('mii-table/assets/table.js', $block, 'js');
        $this->toBlock('mii-table/assets/table.css', $block, 'css');
    }


    protected function doFetch($block)
    {
        $this->toBlock('unfetch/polyfill/index.js', $block, 'js');
    }

    protected function doPromise($block)
    {
        $this->toBlock('promise-polyfill/dist/polyfill.js', $block, 'js');
    }

    protected function doM1k($block)
    {
        $this->toBlock('m1k/dist/m1k.js', $block, 'js');
        $this->toBlock('m1k/dist/m1k.css', $block, 'css');
    }

    protected function doJquery($block)
    {
        $this->toBlock('jquery/dist/jquery.min.js', $block, 'js');
    }


    protected function doJcrop($block)
    {
        $this->toBlock('Jcrop/js/Jcrop.min.js', $block, 'js');
        $this->toBlock('Jcrop/css/Jcrop.min.css', $block, 'css', static function ($text) use ($block) {
            return \str_replace('url(', 'url(/assets/' . $block . '/', $text);
        });
        $this->toAssets('Jcrop/css/Jcrop.gif', $block);
    }


    protected function doFotorama($block)
    {
        $this->toBlock('fotorama/fotorama.js', $block, 'js');

        $this->toBlock('fotorama/fotorama.css', $block, 'css', static function ($text) use ($block) {
            return \str_replace('url(fotorama', 'url(/assets/' . $block . '/fotorama', $text);
        });

        $this->toAssets('fotorama/fotorama.png', $block);
        $this->toAssets('fotorama/fotorama@2x.png', $block);
    }

    protected function doPlupload($block)
    {
        $this->toBlock('plupload/js/plupload.full.min.js', $block, 'js');
        $this->toAssets('plupload/js/Moxie.swf', $block);
        $this->toAssets('plupload/js/Moxie.xap', $block);
    }


    /**
     * @param      $from
     * @param      $block_name
     * @param      $ext
     * @param null $callback
     * @throws Exception
     */
    protected function toBlock($from, string $block_name, string $ext, \Closure $callback = null): void
    {
        if (!\is_array($from)) {
            $from = [$from];
        }

        $dir = $this->output_path . '/' . \implode('/', \explode('_', $block_name));

        if (!\is_dir($dir)) {
            FS::mkdir($dir, 0777, true);
        }

        $to = $dir . '/' . $block_name . '.' . $ext;
        $exist = \file_exists($to);

        $out = '';
        $same = true;

        foreach ($from as $f) {
            if (!\file_exists($this->input_path . '/' . $f)) {
                throw new Exception("Source for $block_name not found. Skip.");
            }

            if (!$exist || \filemtime($this->input_path . '/' . $f) > \filemtime($to)) {
                $same = false;
            }
        }

        if ($same && !$this->force) {
            return;
        }

        foreach ($from as $f) {
            $text = \file_get_contents($this->input_path . '/' . $f);

            if ($callback) {
                $text = $callback($text);
            }

            $out .= $text . "\n";
        }

        \file_put_contents($to, $out);

        $this->changedFiles++;
    }


    protected function toAssets($from, $block_name, \Closure $callback = null)
    {
        if (!\is_array($from)) {
            $from = [$from];
        }

        $dir = $this->output_path . '/' . \implode('/', \explode('_', $block_name)) . '/assets';

        if (!\is_dir($dir)) {
            FS::mkdir($dir);
        }

        foreach ($from as $f) {
            $filename = \basename($f);

            if ($callback) {
                $file = \file_get_contents($this->input_path . '/' . $f);
                \file_put_contents($dir . '/' . $filename, $callback($file));
            } else {
                \copy($this->input_path . '/' . $f, $dir . '/' . $filename);
            }
        }
    }


    /**
     * @param string   $from
     * @param callable $callback
     */
    protected function iterateDir(string $from, callable $callback): void
    {
        $files = \scandir($this->input_path . '/' . $from);
        \array_map(static function ($item) use ($callback) {
            if ($item === '.' || $item === '..') {
                return;
            }

            $callback($item);
        }, $files);
    }
}
