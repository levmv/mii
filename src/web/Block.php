<?php /** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

namespace mii\web;

use Mii;

class Block
{
    // Block name
    public string $__name;

    public bool $__has_parent = false;

    // Full path to php template
    protected ?string $_file = null;

    // Array of local variables
    protected array $_data = [];

    // List of remote assets
    public $__remote_css;
    public $__remote_js;

    // List of inline codes
    public $__inline_js;

    public $_depends = [];

    // Is assigned any values to block ?
    protected bool $_loaded = false;

    protected static array $_global_data = [];

    /**
     * Sets the block name and local data. Blocks should almost
     * always only be created using [Blocks::factory] or [block].
     *
     *
     * @param string $name block name
     */
    public function __construct(string $name)
    {
        $this->__name = $name;
    }


    /**
     * Magic method, searches for the given variable and returns its value.
     * Local variables will be returned before global variables.
     *
     * [!!] If the variable has not yet been set, an exception will be thrown.
     *
     * @param string $key variable name
     * @return  mixed
     * @throws Exception
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * Magic method, calls [Block::set] with the same parameters.
     *
     * @param string $key variable name
     * @param mixed  $value value
     * @return  void
     */
    public function __set(string $key, mixed $value)
    {
        $this->set($key, $value);
    }

    /**
     * Magic method, determines if a variable is set.
     *
     * @param string $key variable name
     * @return  boolean
     */
    public function __isset(string $key): bool
    {
        return (isset($this->_data[$key]));
    }

    /**
     * Magic method, unset a given variable.
     *
     * @param string $key variable name
     * @return  void
     */
    public function __unset(string $key)
    {
        unset($this->_data[$key]);
    }

    /**
     * Magic method, returns the output of [Block::render].
     *
     * @return  string
     * @throws Exception
     */
    public function __toString(): string
    {
        return $this->render();
    }

    public function depends(array $depends): Block
    {
        $this->_depends = \array_unique(\array_merge($this->_depends, $depends));

        foreach ($this->_depends as $depend) {
            Mii::$app->blocks->get($depend)->__has_parent = true;
        }

        return $this;
    }

    public function name(): string
    {
        return $this->__name;
    }


    public function path(): string
    {
        return '/' . \implode('/', \explode('_', $this->__name));
    }


    public function css(string $link): self
    {
        $this->__remote_css[] = $link;
        return $this;
    }

    public function js(string $link, array $options = []): self
    {
        $this->__remote_js[$link] = $options;
        return $this;
    }

    public function inlineJs(string $code, array $options = []): self
    {
        $this->__inline_js[] = [$code, $options];
        return $this;
    }

    public function get(string $key, $default = null)
    {
        if (\array_key_exists($key, $this->_data)) {
            return $this->_data[$key];
        }

        if ($default !== null) {
            return $default;
        }

        throw new Exception("Block variable is not set: $key");
    }

    /**
     * Assigns a variable by name. Assigned values will be available as a
     * variable within the view file:
     *
     * @param mixed $key variable name or an array of variables
     * @param mixed|null $value value
     * @return  $this
     */
    public function set(string|array $key, mixed $value = null): static
    {
        if (\is_array($key)) {
            foreach ($key as $name => $val) {
                $this->_data[$name] = $val;
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
     * @param string $key variable name
     * @param mixed  $value referenced variable
     * @return  $this
     */
    public function bind(string $key, mixed &$value): static
    {
        $this->_data[$key] =&$value;

        return $this;
    }

    public function bindGlobal(string $key, &$value)
    {
        self::$_global_data[$key] = &$value;

        return $this;
    }

    public function loaded(): bool
    {
        return $this->_loaded;
    }


    /**
     * Renders the view object to a string. Global and local data are merged
     * and extracted to create local variables within the view file.
     *
     * [!!] Global variables with the same key name as local variables will be
     * overwritten by the local variable.
     *
     * @param bool $force is force render needed
     * @return  string
     */
    public function render(bool $force = false): string
    {
        if (!$this->_loaded && !$force) {
            return '';
        }

        if (empty($this->_file)) {
            $this->_file = Mii::$app->blocks->getBlockPhpFile($this->__name);

            if ($this->_file === null) {
                throw new Exception('Block ' . $this->__name . ' does not have a php file');
            }
        }

        \assert(
            (config('debug') &&
                ($benchmark = \mii\util\Profiler::start('Block:render', \mii\util\Debug::path($this->_file))))
            || 1
        );
        // Combine local and global data and capture the output
        $c = $this->capture($this->_file);

        \assert((isset($benchmark) && \mii\util\Profiler::stop($benchmark)) || 1);

        return $c;
    }


    /**
     * Captures the output that is generated when a view is included.
     * The view data will be extracted to make local variables.
     *
     * @param string $block_filename filename
     * @return  string
     * @throws \Throwable
     */
    protected function capture(string $block_filename): string
    {
        if (!empty($this->_data)) {
            // Import the view variables to local namespace
            \extract($this->_data, \EXTR_OVERWRITE);
        }

        if (!empty(self::$_global_data)) {
            // Import the global view variables to local namespace
            \extract(self::$_global_data, \EXTR_SKIP | \EXTR_REFS);
        }

        // Capture the view output
        \ob_start();
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
