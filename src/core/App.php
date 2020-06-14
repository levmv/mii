<?php declare(strict_types=1);

namespace mii\core;

use Mii;

/**
 * Class App
 * @property \mii\cache\Cache $cache The cache application component.
 * @property \mii\db\Database $db The database connection.
 * @property ErrorHandler $error;
 */
abstract class App
{
    public ?string $locale = null; //'ru_RU.UTF-8';

    public string $language = 'ru';

    public ?string $timezone = null;

    public $controller;

    public $_config;

    public ?string $base_url = null;


    public function __construct(array $config = [])
    {
        Mii::$app = $this;

        $this->_config = $config;

        if (isset($this->_config['app'])) {
            foreach ($this->_config['app'] as $key => $value)
                $this->$key = $value;
        }

        if ($this->locale !== null)
            \setlocale(LC_TIME, $this->locale);

        if ($this->timezone !== null)
            \date_default_timezone_set($this->timezone);

        $default_components = $this->default_components();

        if (!isset($this->_config['components'])) {
            $this->_config['components'] = [];
        }

        foreach ($default_components as $name => $class) {
            if (!isset($this->_config['components'][$name])) {
                $this->_config['components'][$name] = $class;
            } elseif (\is_array($this->_config['components'][$name]) && !isset($this->_config['components'][$name]['class'])) {
                $this->_config['components'][$name]['class'] = $class;
            }
        }

        $this->error->register();
    }

    abstract function run();

    abstract function default_components(): array;

    private array $_instances = [];

    public function __get($id)
    {
        if (!isset($this->_instances[$id])) {
            $this->_instances[$id] = $this->load_component($id);
        }

        return $this->_instances[$id];
    }


    public function get(string $id)
    {

        if (!isset($this->_instances[$id])) {
            $this->_instances[$id] = $this->load_component($id);
        }

        return $this->_instances[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->_instances[$id]) || isset($this->_config['components'][$id]);
    }


    public function __isset($name)
    {
        return $this->has($name);
    }

    protected function load_component($id)
    {

        $params = [];

        if (!isset($this->_config['components'][$id])) {
            throw new \Exception("Unknown component ID: $id");
        }

        if (\is_array($this->_config['components'][$id])) {
            // a configuration array
            if (isset($this->_config['components'][$id]['class'])) {
                $class = $this->_config['components'][$id]['class'];
                unset($this->_config['components'][$id]['class']);
                $params = $this->_config['components'][$id];
            } else {
                throw new \Exception("The configuration for the \"$id\" component must contain a \"class\" element.");
            }

        } elseif (\is_string($this->_config['components'][$id])) {
            $class = $this->_config['components'][$id];
            $this->_config['components'][$id] = null;
        } elseif (\is_object($this->_config['components'][$id]) AND $this->_config['components'][$id] instanceof \Closure) {

            return \call_user_func($this->_config['components'][$id], []);

        } else {
            throw new \Exception("Unexpected configuration type for the $id component: " . \gettype($this->_config['components'][$id]));
        }

        unset($this->_config['components'][$id]);

        if (\is_string($class)) {
            return new $class($params);
        }

        if (\is_object($class) AND $class instanceof \Closure) {
            return \call_user_func($class, $params);
        }

        throw new \Exception("Cant load component \"$id\" because of wrong type: " . \gettype($class));
    }

}
