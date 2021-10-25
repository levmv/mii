<?php declare(strict_types=1);

namespace mii\web;

use Mii;
use mii\util\HTML;

class StaticBlocks extends BaseBlocks
{
    protected string $assets_map_path = '@tmp';
    protected array $_used_css = [];
    protected array $_used_js = [];
    protected array $assets;

    public function render(): void
    {
        $this->assets_map_path = Mii::resolve($this->assets_map_path);
        $this->assets = require $this->assets_map_path . "/$this->current_set.assets";

        foreach ($this->_blocks as $block_name => $block) {
            if ($block->__has_parent) {
                continue;
            }
            $this->processAssets($block_name, $block_name, $block->_depends);
        }

        $this->_rendered = true;
    }

    /**
     * Recursively process a block and its dependencies
     *
     * @param string $block_name
     * @param string $parent_block
     * @param array  $depends
     */
    public function processAssets(string $block_name, string $parent_block, array $depends): void
    {
        if (isset($this->_used_blocks[$block_name])) {
            return;
        }

        if (!empty($depends)) {
            foreach ($depends as $depend) {
                $this->processAssets($depend, $parent_block, $this->_blocks[$depend]->_depends);
                $this->_used_blocks[$depend] = true;
            }
        }

        if (isset($this->assets['css'][$block_name])) {
            $filename = $this->assets['css'][$block_name];
            if (!isset($this->_used_css[$filename])) {
                $this->_css .= "<link type=\"text/css\" href=\"$this->base_url/$filename.css\" rel=\"stylesheet\">\n";
                $this->_used_css[$filename] = true;
            }
        }

        if (isset($this->assets['js'][$block_name])) {
            $filename = $this->assets['js'][$block_name];
            if (!isset($this->_used_js[$filename])) {
                $this->_js[BaseBlocks::END][] = "<script src=\"$this->base_url/$filename.js\"></script>";
                $this->_used_js[$filename] = true;
            }
        }

        if ($this->_blocks[$block_name]->__remote_js !== null) {
            foreach ($this->_blocks[$block_name]->__remote_js as $link => $settings) {
                if (!empty($settings) && isset($settings['position'])) {
                    $position = $settings['position'];
                    unset($settings['position']);
                } else {
                    $position = BaseBlocks::END;
                }
                $this->_js[$position][] = HTML::script($link, $settings);
            }
        }

        if ($this->_blocks[$block_name]->__remote_css !== null) {
            foreach ($this->_blocks[$block_name]->__remote_css as $link) {
                $this->_css .= '<link type="text/css" href="' . $link . '" rel="stylesheet" />';
            }
        }

        if (!empty($this->_blocks[$block_name]->__inline_js)) {
            foreach ($this->_blocks[$block_name]->__inline_js as $inline) {
                $position = (!empty($inline[1]) and isset($inline[1]['position'])) ? $inline[1]['position'] : BaseBlocks::END;
                $this->_js[$position][] = "<script>{$inline[0]}</script>";
            }
        }
    }
}
