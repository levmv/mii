<?php

namespace mii\web;

use mii\db\Query;
use mii\util\URL;

/**
 * Pagination links generator.
 *
 * @package    Kohana/Pagination
 * @category   Base
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
class Pagination
{


    protected $current_page_source = 'query_string';

    protected $current_page_source_key = 'page';

    // Current page number
    protected $current_page;

    // Total item count
    protected $total_items = 0;

    // How many items to show per page
    protected $items_per_page = 50;

    // Total page count
    protected $total_pages;

    protected $block = 'pagination';

    protected $auto_hide = true;

    protected $first_page_in_url = false;

    // Item offset for the first item displayed on the current page
    protected $current_first_item;

    // Item offset for the last item displayed on the current page
    protected $current_last_item;

    // Previous page number; FALSE if the current page is the first one
    protected $previous_page;

    // Next page number; FALSE if the current page is the last one
    protected $next_page;

    // First page number; FALSE if the current page is the first one
    protected $first_page;

    // Last page number; FALSE if the current page is the last one
    protected $last_page;

    // Query offset
    protected $offset;

    // Route to use for URIs
    protected $route;

    // Parameters to use with Route to create URIs
    protected $route_params = array();

    // Request object
    protected $request;


    /**
     * Creates a new Pagination object.
     *
     * @return  void
     */
    public function __construct(array $config = []) {
        foreach ($config as $key => $val)
            $this->$key = $val;

        if (!$this->request)
            $this->request = \Mii::$app->request;

        // Pagination setup
        $this->calculate();
    }

    /**
     * Loads configuration settings into the object and (re)calculates pagination if needed.
     * Allows you to update config settings after a Pagination object has been constructed.
     *
     * @param   array $config configuration
     * @return  object  Pagination
     */
    public function calculate(array $config = []) {
        foreach ($config as $key => $val)
            $this->$key = $val;

        if ($this->current_page === null) {
            $query_key = $this->current_page_source_key;

            switch ($this->current_page_source) {
                case 'query_string':
                case 'mixed':

                    $this->current_page = (int)$this->request->get($query_key, 1);
                    break;

                case 'route':

                    $this->current_page = (int)$this->request->param($query_key, 1);

                    break;
            }
        }

        // Calculate and clean all pagination variables
        $this->total_items = (int)max(0, $this->total_items);
        $this->items_per_page = (int)max(1, $this->items_per_page);
        $this->total_pages = (int)ceil($this->total_items / $this->items_per_page);
        $this->current_page = (int)min(max(1, $this->current_page), max(1, $this->total_pages));
        $this->current_first_item = (int)min((($this->current_page - 1) * $this->items_per_page) + 1, $this->total_items);
        $this->current_last_item = (int)min($this->current_first_item + $this->items_per_page - 1, $this->total_items);
        $this->previous_page = ($this->current_page > 1) ? $this->current_page - 1 : FALSE;
        $this->next_page = ($this->current_page < $this->total_pages) ? $this->current_page + 1 : FALSE;
        $this->first_page = ($this->current_page === 1) ? FALSE : 1;
        $this->last_page = ($this->current_page >= $this->total_pages) ? FALSE : $this->total_pages;
        $this->offset = (int)(($this->current_page - 1) * $this->items_per_page);


        // Chainable method
        return $this;
    }

    /**
     * Generates the full URL for a certain page.
     *
     * @param   integer  page number
     * @return  string   page URL
     */
    public function url($page = 1) {
        // Clean the page number
        $page = max(1, (int)$page);

        // No page number in URLs to first page
        if ($page === 1 AND !$this->first_page_in_url) {
            $page = NULL;
        }

        switch ($this->current_page_source) {
            case 'query_string':
            case 'mixed':

                return URL::site($this->request->uri() .
                    $this->query([$this->current_page_source_key => $page]));

            case 'route':

                return URL::site($this->route->url(array_merge($this->route_params,
                        array($this->current_page_source_key => $page))) . $this->query());
        }

        return '#';
    }

    /**
     * Checks whether the given page number exists.
     *
     * @param   integer $page page number
     * @return  boolean
     */
    public function valid_page(int $page): bool {
        return $page > 0 AND $page <= $this->total_pages;
    }

    /**
     * Renders the pagination links.
     *
     * @param   mixed   string of the block name to use, or a block object
     * @return  string  pagination output (HTML)
     */
    public function render($block = null) {
        // Automatically hide pagination whenever it is superfluous
        if ($this->auto_hide === true AND $this->total_pages <= 1)
            return '';

        if ($block === null) {
            // Use the view from config
            $block = $this->block;
        }

        if (!$block instanceof Block) {
            // Load the view file
            $block = block($block);
        }

        // Pass on the whole Pagination object
        return $block->set(get_object_vars($this))->set('page', $this)->render();
    }

    public function get_offset() {
        return $this->offset;
    }

    public function get_limit() {
        return $this->items_per_page;
    }

    public function next_page() {
        return $this->next_page;
    }

    public function prev_page() {
        return $this->previous_page;
    }

    public function links(): array {
        $links = [];
        if ($this->next_page()) {
            $links[] = [
                'rel' => 'next',
                'href' => $this->url($this->next_page())
            ];
        }

        if ($this->prev_page()) {
            $links[] = [
                'rel' => 'prev',
                'href' => $this->url($this->prev_page())
            ];
        }
        return [];
    }


    /**
     * URL::query() replacement for Pagination use only
     *
     * @param    array    Parameters to override
     * @return    string
     */
    public function query(array $params = NULL) {
        if ($params === NULL) {
            // Use only the current parameters
            $params = $this->request->get();
        } else {
            // Merge the current and new parameters
            $params = array_merge($this->request->get(), $params);
        }

        if (empty($params)) {
            // No query parameters
            return '';
        }

        // Note: http_build_query returns an empty string for a params array with only NULL values
        $query = http_build_query($params, '', '&');

        // Don't prepend '?' to an empty string
        return ($query === '') ? '' : ('?' . $query);
    }

    /**
     * Renders the pagination links.
     *
     * @return  string  pagination output (HTML)
     */
    public function __toString() {
        return $this->render();
    }

}


