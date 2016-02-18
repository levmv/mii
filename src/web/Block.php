<?php

namespace mii\web;

use Mii;
use mii\web\Exception;


class Block
{

    // Block name
    protected $_name;

    // Block path
    protected $_path;

    // Full path to php template
    protected $_file;

    // Array of local variables
    protected $_data = [];

    // List of remote assets
    protected $_remote_css = false;

    protected $_remote_js = false;

    protected $_depends = [];

    // Is assigned any values to block ?
    protected $_loaded = false;


    /**
     * Sets the block name and local data. Blocks should almost
     * always only be created using [Blocks::factory] or [block].
     *
     *
     * @param   string $name block name
     * @param   string $file path to block php file
     * @param   array $params array of values
     * @return  void
     * @uses    View::set_filename
     */
    public function __construct($name, $file = null, array $values = null)
    {
        $this->_name = $name;

        $this->_file = $file;

        if ($values !== null)
            $this->set($values);
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
    public function & __get($key)
    {
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
    public function __set($key, $value)
    {
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
    public function __isset($key)
    {
        return (isset($this->_data[$key]));
    }

    /**
     * Magic method, unsets a given variable.
     *
     *     unset($view->foo);
     *
     * @param   string $key variable name
     * @return  void
     */
    public function __unset($key)
    {
        unset($this->_data[$key]);
    }

    /**
     * Magic method, returns the output of [View::render].
     *
     * @return  string
     * @uses    View::render
     */
    public function __toString()
    {
        return $this->render();
    }

    public function depends(array $depends)
    {
        $this->_depends = array_unique(array_merge($this->_depends, $depends));

        foreach ($this->_depends as $depend) {
            Mii::$app->blocks->get($depend);
        }

        return $this;
    }

    public function name() {
        return $this->_name;
    }


    public function get_depends() {
        return $this->_depends;
    }

    public function css($link) {
        if(!$this->_remote_css)
            $this->_remote_css = [];

        $this->_remote_css[$link] = true;
        return $this;
    }

    public function js($link) {
        if(!$this->_remote_js)
            $this->_remote_js = [];

        $this->_remote_js[$link] = true;
        return $this;
    }

    public function get_remote_assets() {
        if(!$this->_remote_css AND !$this->_remote_js)
            return false;

        return [$this->_remote_css, $this->_remote_js];
    }


    public function get($key, $default = NULL)
    {

        if (array_key_exists($key, $this->_data)) {
            return $this->_data[$key];
        } else {
            if ($default !== NULL)
                return $default;

            throw new Exception('Block variable is not set: :var',
                [':var' => $key]);
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
     * @param   string $key variable name or an array of variables
     * @param   mixed $value value
     * @return  $this
     */
    public function set($key, $value = NULL)
    {
        if (is_array($key)) {
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
    public function bind($key, & $value)
    {
        $this->_data[$key] =& $value;

        return $this;
    }

    public function loaded()
    {
        return $this->_loaded;
    }


    /**
     * Renders the view object to a string. Global and local data are merged
     * and extracted to create local variables within the view file.
     *
     *     $output = $view->render();
     *
     * [!!] Global variables with the same key name as local variables will be
     * overwritten by the local variable.
     *
     * @param   string $file view filename
     * @return  string
     * @uses    Block::capture
     */
    public function render($force = false)
    {
        if (!$this->_loaded AND !$force) {
            return '';
        }

        if (empty($this->_file)) {
            return '';
        }

        $benchmark = false;
        if (config('profiling'))
            $benchmark = \mii\util\Profiler::start('Block:render', \mii\util\Debug::path($this->_file));

        // Combine local and global data and capture the output
        $c = $this->capture($this->_file);

        if ($benchmark)
            \mii\util\Profiler::stop($benchmark);

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
    protected function capture($block_filename)
    {

        // Import the view variables to local namespace
        extract($this->_data, EXTR_OVERWRITE);

        // Capture the view output
        ob_start();
        ob_implicit_flush(false);

        try {

            // Load the view within the current scope
            require $block_filename;

        } catch (\Exception $e) {

            // Delete the output buffer
            ob_end_clean();

            // Re-throw the exception
            throw $e;
        }

        // Get the captured output and close the buffer
        return ob_get_clean();
    }


}


