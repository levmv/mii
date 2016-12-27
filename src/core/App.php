<?php

namespace mii\core;

use Mii;

/**
 * Class App
 * @property \mii\cache\Cache $cache The cache application component.
 * @property \mii\db\Database $db The database connection.
 * @property \mii\email\PHPMailer $mailer
 * @property \mii\auth\Auth $auth;
 */

abstract class App {


    public $charset = 'UTF-8';

    public $locale; //'ru_RU.UTF-8';

    public $language = 'ru';

    /**
     * @var \mii\core\Container
     */
    public $container;

    public $controller;

    public $_config = [];

    public $base_url = '/';


    public function __construct(array $config = []) {
        Mii::$app = $this;

        $this->init($config);
    }

    public function init(array $config) {

        $this->_config = $config;

        if(isset($this->_config['app'])) {
            foreach($this->_config['app'] as $key => $value)
                $this->$key = $value;
        }

        if($this->locale)
            setlocale(LC_ALL, $this->locale);

        Mii::$container = new Container();

        $components = $this->default_components();

        if(isset($config['components'])) {
            foreach($components as $name => $component) {
                if (!isset($config['components'][$name])) {
                    $config['components'][$name] = $component;
                } elseif (is_array($config['components'][$name]) && !isset($config['components'][$name]['class'])) {
                    $config['components'][$name]['class'] = $component['class'];
                }
            }
            $components = $config['components'];
        }

        foreach($components as $name => $definition) {

            $this->set($name, $definition);
        }

        // register Error handler
        if($this->has('error')) {
            $this->error->register();
        }
    }

    abstract function run();

    public function default_components() {
        return [
            'log' => ['class' => \mii\log\Logger::class],
            'user' => ['class' => 'mii\auth\User'],
            'blocks' => ['class' => 'mii\web\Blocks'],
            'auth' => ['class' => 'mii\auth\Auth'],
            'db' => ['class' => 'mii\db\Database'],
            'cache' => ['class' => 'mii\cache\Apcu'],
            'mailer' => ['class' => 'mii\email\PHPMailer']
        ];
    }


    private $_components = [];

    private $_definitions = [];


    public function __get($name) {
        if ($this->has($name)) {
            return $this->get($name);
        }
    }

    public function has($id, $instantiated = false) {
        return $instantiated
            ? isset($this->_components[$id]) AND \Mii::$container->has($this->_components[$id])
            : isset($this->_components[$id]);
    }


    public function get($id, $throwException = true) {

        if (isset($this->_components[$id])) {
            return Mii::$container->get($id);
        }

        throw new \Exception("Unknown component ID: $id");

        if (isset($this->_definitions[$id])) {
            $definition = $this->_definitions[$id];

            if(is_array($definition)) {
                $class = $definition['class'];
                unset($definition['class']);
            } elseif(is_string($definition)) {
                $this->_components[$id] = $definition;
                return \Mii::$container->get($definition);
            }

            if (is_object($definition) && !$definition instanceof \Closure) {
                return $this->_components[$id] = $definition;
            } else {
                $this->_components[$id] = $class;
                return \Mii::$container->get($class, [$definition]);
            }
        } elseif ($throwException) {
            throw new \Exception("Unknown component ID: $id");
        } else {
            return null;
        }
    }

    public function __isset($name) {
        if ($this->has($name, true)) {
            return true;
        }
        return false;
    }

    public function set($id, $definition) {

        if ($definition === null) {
            unset($this->_components[$id]);
            // todo: remove from container
            return;
        }
        if (is_array($definition)) {
            // a configuration array
            if (isset($definition['class'])) {
                $this->_components[$id] = $definition['class'];
                unset($definition['class']);
                $params = (empty($definition)) ? [] : [$definition];

                \Mii::$container->share($id, $this->_components[$id], $params);
            } else {
                throw new \Exception("The configuration for the \"$id\" component must contain a \"class\" element.");
            }

        } elseif(is_string($definition)) {
            \Mii::$container->share($id, $definition);
            $this->_components[$id] = true;

        } elseif(is_object($definition) || is_callable($definition, true)) {
            // an object, a class name, or a PHP callable
            $this->_components[$id] = $definition;

        } else {
            throw new \Exception("Unexpected configuration type for the \"$id\" component: " . gettype($definition));
        }
        return;

        if ($definition === null) {
            unset($this->_components[$id]);
            // todo: remove from container
            return;
        }

        if (is_array($definition)) {
            // a configuration array
            if (isset($definition['class'])) {
                $this->_definitions[$id] = $definition;

                \Mii::$container->share($id, $definition, [$definition]);
            } else {
                throw new \Exception("The configuration for the \"$id\" component must contain a \"class\" element.");
            }
        }

        $this->_components[$id] = true;

    }

}