<?php

namespace mii\web;

use Mii;
use mii\util\FS;
use mii\util\HTML;
use mii\util\Text;

class Blocks extends BaseBlocks
{
    public $base_path;

    public function render(): void {

        if ($this->base_path === null) {
            $this->base_path = '@pub' . $this->base_url;
        }
        $this->base_path = Mii::resolve($this->base_path);

        if (!is_dir($this->base_path))
            FS::mkdir($this->base_path, 0777, true);

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

    private function _build_block(string $block_name, string $type, array $files): void {

        $result_file_name = $block_name . '.' . substr(Text::base64url_encode(md5(implode('', array_keys($files)), true)), 0, 8);

        $web_output = $this->base_url . '/' . $result_file_name . '.' . $type;
        $output = $this->base_path . '/' . $result_file_name . '.' . $type;

        $need_recompile = !is_file($output);

        if(!$need_recompile) {
            $mtime_output = filemtime($output);
            foreach ($files as $file) {
                if ($mtime_output < filemtime($file)) {
                    $need_recompile = true;
                    break;
                }
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


    private function _build_assets_dir(string $blockname, string $path): void {

        $output = $this->base_path . '/' . $blockname;

        if (!is_link($output)) {
            symlink($path, $output);
        }
    }
}

