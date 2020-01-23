<?php

namespace mii\web;

use Mii;
use mii\core\ErrorHandler;


class Block
{
    // Block name
    public $__name;

    public $__has_parent;

    // Full path to php template
    protected $_file;

    // Array of local variables
    protected $_data = [];

    // List of remote assets
    public $__remote_css;
    public $__remote_js;

    // List of inline codes
    public $__inline_css;
    public $__inline_js;

    public $_depends = [];

    // Is assigned any values to block ?
    protected $_loaded = false;

    protected static $_global_data = [];

    /**
     * Sets the block name and local data. Blocks should almost
     * always only be created using [Blocks::factory] or [block].
     *
     *
     * @param   string $name block name
     * @param   string $file path to block php file
     * @return  void
     */
    public function __construct(string $name, ?string $file = null) {
        $this->__name = $name;
        $this->_file = $file;
    }


    /**
     * Magic method, searches for the given variable and returns its value.
     * Local variables will be returned before global variables.
     *
     *     $value = $view->foo;
     *
     * [!!] If the variable has not yet been set, an exception will be thrown.
     *
     * @param   string $key variable name
     * @return  mixed
     */
    public function & __get($key) {
        return $this->get($key);
    }

    /**
     * Magic method, calls [Block::set] with the same parameters.
     *
     *     $view->foo = 'something';
     *
     * @param   string $key variable name
     * @param   mixed $value value
     * @return  void
     */
    public function __set($key, $value) {
        $this->set($key, $value);
    }

    /**
     * Magic method, determines if a variable is set.
     *
     *     isset($block->foo);
     *
     * [!!] `NULL` variables are not considered to be set by [isset](http://php.net/isset).
     *
     * @param   string $key variable name
     * @return  boolean
     */
    public function __isset(string $key): bool {
        return (isset($this->_data[$key]));
    }

    /**
     * Magic method, unset a given variable.
     *
     *     unset($view->foo);
     *
     * @param   string $key variable name
     * @return  void
     */
    public function __unset(string $key) {
        unset($this->_data[$key]);
    }

    /**
     * Magic method, returns the output of [Block::render].
     *
     * @return  string
     */
    public function __toString(): string {
        return $this->render();
    }

    public function depends(array $depends): Block {
        $this->_depends = array_unique(array_merge($this->_depends, $depends));

        foreach ($this->_depends as $depend) {
            Mii::$app->blocks->get($depend)->__has_parent = true;
        }

        return $this;
    }

    public function name(): string {
        return $this->__name;
    }


    public function path(): string {
        return '/' . implode('/', explode('_', $this->__name));
    }


    public function css(string $link) {
        $this->__remote_css[] = $link;
        return $this;
    }

    public function js(string $link, array $options = []) {
        $this->__remote_js[$link] = $options;
        return $this;
    }

    public function inline_css(string $code, array $options = []) {
        $this->__inline_css[] = [$code, $options];
        return $this;
    }

    public function inline_js(string $code, array $options = []) {
        $this->__inline_js[] = [$code, $options];
        return $this;
    }

    public function get(string $key, $default = NULL) {

        if (\array_key_exists($key, $this->_data)) {
            return $this->_data[$key];
        } else {
            if ($default !== NULL)
                return $default;

            throw new Exception("Block variable is not set: $key");
        }
    }

    /**
     * Assigns a variable by name. Assigned values will be available as a
     * variable within the view file:
     *
     *     // This value can be accessed as $foo within the view
     *     $view->set('foo', 'my value');
     *
     * You can also use an array to set several values at once:
     *
     *     // Create the values $food and $beverage in the view
     *     $view->set(array('food' => 'bread', 'beverage' => 'water'));
     *
     * @param   mixed $key variable name or an array of variables
     * @param   mixed $value value
     * @return  $this
     */
    public function set($key, $value = NULL) {
        if (\is_array($key)) {
            foreach ($key as $name => $value) {
                $this->_data[$name] = $value;
            }
        } else {
            $this->_data[$key] = $value;
        }

        $this->_loaded = true;

        return $this;
    }

    /**
     * Assigns a value by reference. The benefit of binding is that values can
     * be altered without re-setting them. It is also possible to bind variables
     * before they have values. Assigned values will be available as a
     * variable within the view file:
     *
     *     // This reference can be accessed as $ref within the view
     *     $view->bind('ref', $bar);
     *
     * @param   string $key variable name
     * @param   mixed $value referenced variable
     * @return  $this
     */
    public function bind(string $key, & $value) {
        $this->_data[$key] =& $value;

        return $this;
    }

    public function bind_global(string $key, & $value) {
        Block::$_global_data[$key] = &$value;

        return $this;
    }

    public function loaded(): bool {
        return (bool)$this->_loaded;
    }


    /**
     * Renders the view object to a string. Global and local data are merged
     * and extracted to create local variables within the view file.
     *
     *     $output = $block->render();
     *
     * [!!] Global variables with the same key name as local variables will be
     * overwritten by the local variable.
     *
     * @param   bool $force is force render needed
     * @return  string
     * @uses    Block::capture
     */
    public function render(bool $force = false): string {
        if (!$this->_loaded AND !$force) {
            return '';
        }

        if (empty($this->_file)) {

            $this->_file = Mii::$app->blocks->get_block_php_file($this->__name);

            if($this->_file === null)
                throw new Exception('Block '.$this->__name.' does not have a php file');
        }

        assert(
            config('debug') &&
            ($benchmark = \mii\util\Profiler::start('Block:render', \mii\util\Debug::path($this->_file)))
            || true
        );
        // Combine local and global data and capture the output
        $c = $this->capture($this->_file);

        assert(isset($benchmark) && \mii\util\Profiler::stop($benchmark) || true);

        return $c;
    }


    /**
     * Captures the output that is generated when a view is included.
     * The view data will be extracted to make local variables.
     *
     * @param   string $block_filename filename
     * @throws \Exception
     * @return  string
     */
    protected function capture(string $block_filename): string {

        if(!empty($this->_data)) {
            // Import the view variables to local namespace
            \extract($this->_data, EXTR_OVERWRITE);
        }

        if (!empty(Block::$_global_data)) {
            // Import the global view variables to local namespace
            \extract(Block::$_global_data, EXTR_SKIP | EXTR_REFS);
        }

        // Capture the view output
        \ob_start();
        \ob_implicit_flush(0);

        try {

            // Load the view within the current scope
            require $block_filename;

        } catch (\Throwable $e) {

            // Delete the output buffer
            \ob_end_clean();

            // Re-throw the exception
            throw $e;
        }

        // Get the captured output and close the buffer
        return \ob_get_clean();
    }


}


