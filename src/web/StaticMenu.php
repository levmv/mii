<?php

namespace mii\web;

class StaticMenu extends Menu {

    public $params = array(
        'with_main' => true,
        'absolute' => false,
        'with_main2' => false
    );

    public function as_array()
    {

        $current = ($this->current_item) ? $this->current_item :  Mii\ORM::factory('Page') ;

        $level = ($current->level == 0) ? 1 : $current->level;


        $static_menu = array(
            'news' => 'Новости',
            'events' => 'Афиша',
            'companies' => 'Организации',
            //'realty' => 'Недвижимость',
            // 'streets' => 'Адреса',
            'city' => 'Город'
            //'http://ny2015.start33.ru/' => 'Новый год'

        );

        $menu = array();

        if($this->params['with_main2']) {
            $menu[] = [
                'name' => 'Главная',
                'url'     => 'http://start33.ru/',
                'children'=> array(),
                'active'  => false,
                'current' => false
            ];
        }

        foreach($static_menu as $slug => $name) {

            $url = ($this->params['absolute']) ? 'http://start33.ru/' . $slug : '/' . $slug;
            $active = $this->active('/'.$slug);

            $menu[] = array( 'name'    => $name,
                             'url'     => $url,
                             'children'=> array(),
                             'active'  => ($active == Mii\Menu::ACTIVE_ITEM),
                             'current' => ($active == Mii\Menu::CURRENT_ITEM));

        }



        return $menu;
    }






}