<?php

namespace mii\core;

use Mii;

/**
 * Class App
 * @property \mii\cache\Cache $cache The cache application component.
 * @property \mii\db\Database $db The database connection.
 * @property \mii\email\Mailer $mailer
 * @property \mii\auth\Auth $auth;
 * @property ErrorHandler $error;
 */
abstract class App
{

    public $charset = 'UTF-8';

    public $locale; //'ru_RU.UTF-8';

    public $language = 'ru';

    public $timezone;

    /**
     * @var \mii\core\Container
     */
    public $container;

    public $controller;

    public $_config;

    public $base_url = '/';


    public function __construct(array $config = []) {
        Mii::$app = $this;

        $this->_config = $config;

        if (isset($this->_config['app'])) {
            foreach ($this->_config['app'] as $key => $value)
                $this->$key = $value;
        }

        if ($this->locale)
            setlocale(LC_ALL, $this->locale);

        if ($this->timezone)
            date_default_timezone_set($this->timezone);

        if ($this->container !== null) {
            if ($this->container === true) {
                $this->container = new Container();
            } elseif (is_string($this->container)) {
                $class = $this->container;
                $this->container = new $class;
            }
        }

        $default_components = $this->default_components();

        if (!isset($this->_config['components'])) {
            $this->_config['components'] = [];
        }

        foreach ($default_components as $name => $class) {
            if (!isset($this->_config['components'][$name])) {
                $this->_config['components'][$name] = $class;
            } elseif (is_array($this->_config['components'][$name]) && !isset($this->_config['components'][$name]['class'])) {
                $this->_config['components'][$name]['class'] = $class;
            }
        }
        // register Error handler
        if ($this->has('error')) {
            $this->error->register();
        }
    }

    abstract function run();

    public function default_components() : array {
        return [
            'log' => 'mii\log\Logger',
            'blocks' => 'mii\web\Blocks',
            'auth' => 'mii\auth\Auth',
            'router' => 'mii\core\Router',
            'db' => 'mii\db\Database',
            'cache' => 'mii\cache\Apcu',
            'mailer' => 'mii\email\PHPMailer'
        ];
    }


    private $_components = [];
    private $_instances = [];


    public function __get($name) {
        return $this->get($name);
    }

    public function has(string $id): bool {
        return isset($this->_components[$id]) || isset($this->_config['components'][$id]);
    }


    public function get(string $id) {

        if (!isset($this->_instances[$id])) {
            $this->_instances[$id] = $this->load_component($id);
        }

        return $this->_instances[$id];
    }

    public function __isset($name) {
        return $this->has($name);
    }

    protected function load_component($id) {

        $params = [];

        if(!isset($this->_config['components'][$id])) {
            throw new \Exception("Unknown component ID: $id");
        }

        if (is_array($this->_config['components'][$id])) {
            // a configuration array
            if (isset($this->_config['components'][$id]['class'])) {
                $class = $this->_config['components'][$id]['class'];
                unset($this->_config['components'][$id]['class']);
                $params = $this->_config['components'][$id];
            } else {
                throw new \Exception("The configuration for the \"$id\" component must contain a \"class\" element.");
            }

        } elseif (is_string($this->_config['components'][$id])) {
            $class = $this->_config['components'][$id];
            $this->_config['components'][$id] = null;
        } elseif (is_object($this->_config['components'][$id]) AND $this->_config['components'][$id] instanceof \Closure) {

            return call_user_func($this->_config['components'][$id], $this);

        } else {
            throw new \Exception("Unexpected configuration type for the $id component: " . gettype($this->_config['components'][$id]));
        }

        unset($this->_config['components'][$id]);

        if (is_string($class)) {
            return new $class($params);
        }

        throw new \Exception("Cant load component \"$id\" because of wrong type: " . gettype($class));
    }

}