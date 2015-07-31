<?php

namespace mii\web;

use mii\util\URL;

class Menu
{

    const CURRENT_ITEM = 1;
    const ACTIVE_ITEM = 2;

    /**
     * @var array Array of list items
     *
     * [[
     *      'name' => 'Menu item name',
     *      'url' => 'item url',
     *      'current' => bool (current flag)
     *      'active' => bool (active flag)
     * ], ...]
     *
     */
    public $items = [];

    protected $_current_item;

    protected $_current_uri;

    protected $_block_name;

    /**
     * Current element may be:
     *  string URI
     *  array with 'url' key
     *  object with 'url' method
     *
     * @param string|array|object $current
     * @param string $block_name
     * @param array $params
     */

    public function __construct($items = null, $block_name = null, $current = null)
    {
        $this->_current_uri = trim(URL::site(\Mii::$app->request->uri()), '/');

        if ($items) {
            $this->items = $items;
        }

        if($block_name) {
            $this->_block_name = $block_name;
        }

        $this->process();
    }

    public function process() {

    }

    public function current_item($current = null)
    {

        if ($current === null) {

            return $this->_current_item;

        } elseif (is_object($current)) {

            $this->_current_item = $current->url();

        } elseif (is_array($current)) {

            $this->_current_item = $current['url'];

        } else {

            $this->_current_item = $current;
        }
    }


    /**
     * Return Array with menu structure
     *
     * @return  Array  menu structure
     */
    public function as_array()
    {
        $menu = [];
        foreach($this->items as $item) {

            $active = $this->active($item['url']);

            $children = isset($item['children'])
                ? $children = (new Menu($item['children']))->as_array()
                : [];

            $menu[] = array( 'name'    => $item['name'],
                             'url'     => $item['url'],
                             'children'=> $children,
                             'active'  => ($active === Menu::ACTIVE_ITEM),
                             'current' => ($active === Menu::CURRENT_ITEM));
        }
        return $menu;
    }


    public function render($block_name = null)
    {
        if ($block_name) {
            $this->_block_name = $block_name;
        }

        return block($this->_block_name)->set('list', $this->as_array())->render();
    }


    public function __toString()
    {
        try {
            return $this->render();
        } catch (\Exception $e) {

            /**
             * Display the exception message.
             *
             * We use this method here because it's impossible to throw and
             * exception from __toString().
             */

            for ($level = ob_get_level(); $level > 0; --$level) {
                if (!@ob_end_clean()) {
                    ob_clean();
                }
            }
            return Exception::handler($e)->body();
        }
    }



    /**
     * Determines if the menu item is part of the current URI
     *
     * @param   string  Url of item to check against
     * @return  mixed   Returns Menu::CURRENT_ITEM for current, Menu::ACTIVE_ITEM for active, or 0
     */
    protected function active($url)
    {
        $link = trim(URL::site($url), '/');
        // Exact match (removes default 'index' action)
        if ($this->_current_uri === $link OR preg_replace('~/?index/?$~', '', $this->_current_uri) === $link) {
            return Menu::CURRENT_ITEM;
        } // Checks if it is part of the active path
        else {
            $current_pieces = explode('/', $this->_current_uri);
            array_shift($current_pieces);
            $link_pieces = explode('/', $link);
            array_shift($link_pieces);

            for ($i = 0, $l = count($link_pieces); $i < $l; $i++) {
                if ((isset($current_pieces[$i]) AND $current_pieces[$i] !== $link_pieces[$i]) OR empty($current_pieces[$i])) {
                    return 0;
                }
            }
            return Menu::ACTIVE_ITEM;
        }

        return 0;
    }


}