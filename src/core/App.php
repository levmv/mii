<?php /** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);

namespace mii\core;

use Mii;

/**
 * Class App
 * @property \mii\cache\Cache $cache The cache application component.
 * @property \mii\db\Database $db The database connection.
 * @property ErrorHandler $error;
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
abstract class App
{
    public ?string $locale = null; //'ru_RU.UTF-8';

    public string $language = 'ru';

    public array $_config;

    public ?string $base_url = null;


    public function __construct(array $config = [])
    {
        Mii::$app = $this;

        $this->_config = $config;

        if (isset($this->_config['app'])) {
            foreach ($this->_config['app'] as $key => $value) {
                $this->$key = $value;
            }
        }

        if ($this->locale !== null) {
            \setlocale(\LC_TIME, $this->locale);
        }

        $default_components = $this->defaultComponents();

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

    abstract public function run(): void;

    abstract protected function defaultComponents(): array;

    private array $_instances = [];

    // We keep old `id` references for compatibility reasons and have parallel `classes` links.
    // So, most legacy code work as usual, but now we have a little more DI
    private array $_classToId = [];

    public function __get($id)
    {
        if (!isset($this->_instances[$id])) {
            $this->_instances[$id] = $this->loadComponent($id);
        }

        return $this->_instances[$id];
    }


    public function get(string $id)
    {
        if (!isset($this->_instances[$id])) {
            $this->_instances[$id] = $this->loadComponent($id);
        }

        return $this->_instances[$id];
    }

    public function setInstance(string $id, $component)
    {
        return $this->_instances[$id] = $component;
    }

    public function getByClass(string $class)
    {
        return $this->get($this->_classToId[$class]);
    }

    public function has(string $id): bool
    {
        return isset($this->_config['components'][$id])
            || isset($this->_classToId[$id])
            || !$this->_processed && $this->preprocessComponents() && isset($this->_classToId[$id]);
    }

    public function hasClass(string $class): bool
    {
        return isset($this->_classToId[$class]) || $this->preprocessComponents() && isset($this->_classToId[$class]);
    }

    public function hasInstance(string $id): bool
    {
        return isset($this->_instances[$id]);
    }


    public function __isset($name)
    {
        return $this->has($name);
    }


    protected bool $_processed = false;

    public function preprocessComponents()
    {
        if (!$this->_processed) {
            foreach ($this->_config['components'] as $id => $conf) {
                if (\is_array($conf) && isset($conf['class'])) {
                    $this->_classToId[$conf['class']] = $id;
                } elseif (\is_string($conf)) {
                    $this->_classToId[$conf] = $id;
                }
            }
            $this->_processed = true;
        }
        return true;
    }

    /**
     * @throws \Exception
     */
    protected function loadComponent($id)
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
        } elseif (\is_object($this->_config['components'][$id]) && $this->_config['components'][$id] instanceof \Closure) {
            return \call_user_func($this->_config['components'][$id], []);
        } else {
            throw new \Exception("Unexpected configuration type for the $id component: " . \get_debug_type($this->_config['components'][$id]));
        }

        if (\is_string($class)) {
            $this->_classToId[$class] = $id;
            return new $class($params);
        }

        throw new \Exception("Cant load component \"$id\" because of wrong type: " . \get_debug_type($class));
    }
}
