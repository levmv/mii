<?php

namespace mii\core;

use Mii;

/**
 * Class App
 * @property \mii\cache\Cache $cache The cache application component.
 * @property \mii\db\Database $db The database connection.
 * @property \mii\email\PHPMailer $mailer
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

        $this->init($config);
    }

    public function init(array $config) {

        $this->_config = $config;

        if (isset($this->_config['app'])) {
            foreach ($this->_config['app'] as $key => $value)
                $this->$key = $value;
        }

        if ($this->locale)
            setlocale(LC_ALL, $this->locale);

        if ($this->timezone)
            date_default_timezone_set($this->timezone);

        if ($this->container) {
            if ($this->container === true) {
                $this->container = new Container();
            } elseif (is_string($this->container)) {
                $class = $this->container;
                $this->container = new $class;
            }
        }

        $components = $this->default_components();

        if (isset($this->_config['components'])) {
            foreach ($components as $name => $component) {
                if (!isset($this->_config['components'][$name])) {
                    $this->_config['components'][$name] = $component;
                } elseif (is_array($this->_config['components'][$name]) && !isset($this->_config['components'][$name]['class'])) {
                    $this->_config['components'][$name]['class'] = $component['class'];
                }
            }
        }

        if ($this->container === null) {
            $components = array_keys($this->_config['components']);
            foreach ($components as $id) {
                $this->inner_set($id);
            }
        } else {
            foreach ($this->_config['components'] as $name => $definition) {
                $this->set($name, $definition);
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
            'log' => ['class' => 'mii\log\Logger'],
            'blocks' => ['class' => 'mii\web\Blocks'],
            'auth' => ['class' => 'mii\auth\Auth'],
            'router' => ['class' => 'mii\core\Router'],
            'db' => ['class' => 'mii\db\Database'],
            'cache' => ['class' => 'mii\cache\Apcu'],
            'mailer' => ['class' => 'mii\email\PHPMailer']
        ];
    }


    private $_components = [];
    private $_instances = [];


    public function __get($name) {

        if ($this->has($name)) {
            return $this->get($name);
        }
        return false;
    }

    public function has(string $id): bool {
        return isset($this->_components[$id]);
    }


    public function get(string $id) {

        if ($this->container === null) {
            if (isset($this->_components[$id])) {

                if (isset($this->_instances[$id]))
                    return $this->_instances[$id];

                $this->_instances[$id] = $this->load_component($id);

                return $this->_instances[$id];
            }

        } else {
            if (isset($this->_components[$id])) {
                return $this->container->get($id);
            }
        }


        throw new \Exception("Unknown component ID: $id");
    }

    public function __isset($name) {
        return $this->has($name);
    }

    public function inner_set($id, $definition = null): void {

        if ($definition !== null)
            $this->_config['components'][$id] = $definition;

        if (is_array($this->_config['components'][$id])) {
            // a configuration array
            if (isset($this->_config['components'][$id]['class'])) {
                $this->_components[$id] = $this->_config['components'][$id]['class'];
                unset($this->_config['components'][$id]['class']);
            } else {
                throw new \Exception("The configuration for the \"$id\" component must contain a \"class\" element.");
            }

        } elseif (is_string($this->_config['components'][$id])) {
            $this->_components[$id] = $this->_config['components'][$id];
            $this->_config['components'][$id] = null;
        } elseif (is_object($this->_config['components'][$id]) AND $this->_config['components'][$id] instanceof \Closure) {
            $this->_components[$id] = true;
        } else {
            throw new \Exception("Unexpected configuration type for the $id component: " . gettype($this->_config['components'][$id]));
        }

    }

    public function set($id, $definition): void {

        if ($this->container === null) {
            $this->inner_set($id, $definition);
            return;
        }

        if ($definition === null) {
            unset($this->_components[$id]);
            $this->container->clear($id);
            return;
        }
        if (is_array($definition)) {
            // a configuration array
            if (isset($definition['class'])) {
                $this->_components[$id] = $definition['class'];
                unset($definition['class']);
                $params = (empty($definition)) ? [] : [$definition];

                $this->container->share($id, $this->_components[$id], $params);
            } else {
                throw new \Exception("The configuration for the \"$id\" component must contain a \"class\" element.");
            }

        } elseif (is_string($definition)) {
            $this->container->share($id, $definition);
            $this->_components[$id] = true;

        } elseif (is_callable($definition, true)) {
            $this->_components[$id] = $definition;
        } else {
            throw new \Exception("Unexpected configuration type for the \"$id\" component: " . gettype($definition));
        }
    }

    protected function load_component($id) {

        $class = $this->_components[$id];

        if (is_string($class)) {

            if (!empty($this->_config['components'][$id]))
                return new $class($this->_config['components'][$id]);

            return new $class();
        }

        if (is_object($class) && $class instanceof \Closure) {
            return call_user_func($class, $this);
        }

        throw new \Exception("Cant load component \"$id\" because of wrong type: " . gettype($class));
    }

}