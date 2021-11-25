<?php declare(strict_types=1);

namespace mii\web;

use mii\core\InvalidRouteException;
use mii\util\HTML;
use mii\util\Url;

/**
 * Pagination links generator.
 */
class Pagination
{
    protected string $page_param = 'page';

    // Current page number
    protected ?int $current_page = null;

    // Total item count
    protected int $total_items = 0;

    // How many items to show per page
    protected int $items_per_page = 50;

    // Total page count
    protected int $total_pages;

    protected $block = 'pagination';

    // Previous page number; FALSE if the current page is the first one
    protected $previous_page;

    // Next page number; FALSE if the current page is the last one
    protected $next_page;

    // First page number; FALSE if the current page is the first one
    protected $first_page;

    // Last page number; FALSE if the current page is the last one
    protected $last_page;

    // Query offset
    protected int $offset;

    // Name of route to use for URIs
    protected ?string $route = null;

    // Parameters to use with Route to create URIs
    protected array $route_params = [];

    // Request object
    protected ?Request $request = null;

    protected ?string $base_uri = null;

    protected string $base_class = 'pagination';

    /**
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        foreach ($config as $key => $val) {
            $this->$key = $val;
        }

        if ($this->request === null) {
            $this->request = \Mii::$app->request;
        }

        if ($this->base_uri === null) {
            $this->base_uri = $this->request->uri();
        }

        if ($this->current_page === null) {
            if ($this->route !== null) {
                $this->current_page = (int)$this->request->param($this->page_param, 1);
            } else {
                $this->current_page = (int)$this->request->get($this->page_param, 1);
            }
        }

        // Calculate and clean all pagination variables
        $this->total_items = \max(0, $this->total_items);
        $this->items_per_page = (int)\max(1, $this->items_per_page);
        $this->total_pages = (int)\ceil($this->total_items / $this->items_per_page);
        $this->current_page = (int)\min(\max(1, $this->current_page), \max(1, $this->total_pages));
        $this->previous_page = ($this->current_page > 1) ? $this->current_page - 1 : false;
        $this->next_page = ($this->current_page < $this->total_pages) ? $this->current_page + 1 : false;
        $this->first_page = ($this->current_page === 1) ? false : 1;
        $this->last_page = ($this->current_page >= $this->total_pages) ? false : $this->total_pages;
        $this->offset = ($this->current_page - 1) * $this->items_per_page;
    }


    /**
     * Generates the full URL for a certain page.
     *
     * @param int|null $page
     * @return  string   page URL
     * @throws InvalidRouteException
     */
    public function url(?int $page = 1): string
    {
        // Clean the page number
        $page = \max(1, $page);

        // No page number in URLs to first page
        if ($page === 1) {
            $page = null;
        }

        if ($this->route !== null) {
            return \Mii::$app->router->url($this->route, \array_merge(
                    $this->route_params,
                    [$this->page_param => $page]
                )) . $this->query();
        }

        return Url::site($this->base_uri . $this->query([$this->page_param => $page]));
    }

    /**
     * Checks whether the given page number exists.
     *
     * @param integer $page page number
     * @return  boolean
     */
    public function valid_page(int $page): bool
    {
        return $page > 0 and $page <= $this->total_pages;
    }

    /**
     * Renders the pagination links.
     *
     * @param mixed   string of the block name to use, or a block object
     * @return  string  pagination output (HTML)
     * @throws Exception
     */
    public function render($block = null): string
    {
        // Automatically hide pagination whenever it is superfluous
        if ($this->total_pages <= 1) {
            return '';
        }

        $block = $block ?? $this->block;

        if($block === null) {
            return $this->renderHtml();
        }

        if (!$block instanceof Block) {
            // Load the view file
            $block = block($block);
        }

        // Pass on the whole Pagination object
        return $block->set(\get_object_vars($this))->set('page', $this)->render();
    }

    public function renderHtml() : string
    {
        $MAX_OFFSET = 5;

        $result = "<div class='$this->base_class'>";

        $start = $this->current_page > $MAX_OFFSET
            ? $this->current_page - $MAX_OFFSET
            : 1;

        $right = $this->current_page + $MAX_OFFSET;
        for ($i = $start; $i <= $right && $i <= $this->total_pages; $i++) {
            if ($i === $this->current_page) {
                $result .= "<span class='{$this->base_class}__current'>$i</span>";
            } else {
                $result .= "<a class='{$this->base_class}__link' href='".HTML::chars($this->url($i)) ."'>$i</a>";
            }
        }

        $result .= '</div>';

        return $result;
    }


    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getLimit(): int
    {
        return $this->items_per_page;
    }

    public function nextPage()
    {
        return $this->next_page;
    }

    public function prevPage()
    {
        return $this->previous_page;
    }

    /**
     * URL::query() replacement for Pagination use only
     *
     * @param array|null $params
     * @return    string
     */
    public function query(array $params = null): string
    {
        if ($params === null) {
            // Use only the current parameters
            $params = $this->request->get();
        } else {
            // Merge the current and new parameters
            $params = \array_merge($this->request->get(), $params);
        }

        if (empty($params)) {
            // No query parameters
            return '';
        }

        // Note: http_build_query returns an empty string for a params array with only NULL values
        $query = \http_build_query($params);

        // Don't prepend '?' to an empty string
        return ($query === '') ? '' : ('?' . $query);
    }

    /**
     * Renders the pagination links.
     *
     * @return  string  pagination output (HTML)
     * @throws Exception
     */
    public function __toString()
    {
        return $this->render();
    }
}
