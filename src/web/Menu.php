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


    protected $_data = [];

    protected $_current_item;
    protected $_current_url;
    protected $_current_id;

    protected $_uri;

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

    public function __construct($items = null, $block_name = null, $current_id = null, $current_url = null)
    {
        $this->_uri = trim(URL::site(\Mii::$app->request->uri()), '/');

        if ($items) {
            $this->items = $items;
        }

        if($block_name) {
            $this->_block_name = $block_name;
        }

        if($current_id !== null) {
            $this->current_id($current_id);
        }

        if($current_url !== null) {
            $this->current_url($current_url);
        }
    }

    public function on_render() {

    }

    /**
     * @deprecated
     *
     *
     */
    public function current_item($current = null)
    {
        return $this->current_url($current);
    }

    public function current_url($current_url = null) {

        if ($current_url === null) {

            return $this->_current_item;

        } elseif (is_object($current_url)) {

            $this->_current_item = $current_url;
            $this->_current_item_url = $current_url->url();

        } elseif (is_array($current_url)) {

            $this->_current_item = $current_url;
            $this->_current_item_url = $current_url['url'];

        } else {

            $this->_current_item = $current_url;
        }
    }



    public function current_id($current_id = null) {
        if ($current_id === null) {

            return $this->_current_id;

        } elseif (is_object($current_id)) {

            $this->_current_item = $current_id;
            $this->_current_id = $current_id->id;

        } elseif (is_array($current_id)) {

            $this->_current_item = $current_id;
            $this->_current_id = $current_id['id'];

        } else {

            $this->_current_id = $current_id;
        }
    }


    /**
     * Return Array with menu structure
     *
     * @return  array  menu structure
     */
    public function as_array() : array
    {
        $menu = [];
        foreach($this->items as $item) {

            $active = $this->active($item);


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

    public function get($key, $default = NULL)
    {

        if (array_key_exists($key, $this->_data)) {
            return $this->_data[$key];
        } else {
            if ($default !== NULL)
                return $default;

            throw new Exception('Menu variable is not set: :var',
                [':var' => $key]);
        }
    }

    public function set($key, $value = NULL)
    {
        if (is_array($key)) {
            foreach ($key as $name => $value) {
                $this->_data[$name] = $value;
            }
        } else {
            $this->_data[$key] = $value;
        }

        return $this;
    }


    public function render($block_name = null)
    {
        $this->on_render();


        if ($block_name) {
            $this->_block_name = $block_name;
        }

        return block($this->_block_name)
                ->set('list', $this->as_array())
                ->render();
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
            ErrorHandler::convert_to_error($e);
            return '';
        }
    }


    protected function active($value) {

        if($this->_current_id !== null)
            return $this->is_active_id($value['id']);

        return $this->is_active_url($value['url']);
    }


    /**
     * Determines if the menu item is part of the current URI
     *
     * @param   string  Url of item to check against
     * @return  mixed   Returns Menu::CURRENT_ITEM for current, Menu::ACTIVE_ITEM for active, or 0
     */
    protected function is_active_url($url)
    {
        $uri = ($this->_current_url !== null) ? $this->_current_url : $this->_uri;

        $link = trim(URL::site($url), '/');

        // Exact match
        if ($uri === $link) {
            return Menu::CURRENT_ITEM;
        } // Checks if it is part of the active path
        else {
            $current_pieces = explode('/', $uri);
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

    protected function is_active_id($id)
    {
        return ($this->_current_id == $id) ? Menu::CURRENT_ITEM : 0;

    }



}