<?php

namespace mii\admin;

use mii\web\Block;

class Panel {

    public $hide = false;

    protected $panel_block;
    protected $blocks = [];



    /**
     * @param $path
     * @return AdminPanel
     */
    public function init($block_name = 'admin_panel')
    {
        $this->panel_block = block($block_name )
            ->depends(['i_jquery','i_admin_button','i_admin_icon'])
            ->bind('blocks', $this->blocks)
            ->bind('user', \Mii::$app->user);
    }

    public static function set($name,$value)
    {
        if(self::$panel_block)
            self::$panel_block->set($name,$value);
    }


    public function add($name, $value=false) {
        if(!$this->panel_block)
            return;

        if($name instanceof Block) {
            $this->blocks[] = $name;
            return;
        }

        $this->blocks[] = block($name)->set('value',$value);


    }


    public function render() {
        if($this->panel_block AND !$this->hide) {

            if( (bool) $this->blocks OR (\Mii::$app->user AND \Mii::$app->user->has_role('admin')))
                return $this->panel_block->render(true);
        }

        return '';
    }


}
