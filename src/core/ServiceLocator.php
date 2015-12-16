<?php

namespace mii\core;

use Closure;
use Mii;

class ServiceLocator
{

    private $_components = [];

    private $_definitions = [];


    public function __get($name) {
        if ($this->has($name)) {
            return $this->get($name);
        }
    }

    public function has($id, $instantiated = false) {
        return $instantiated ? isset($this->_components[$id]) : isset($this->_definitions[$id]);
    }

    public function get($id, $throwException = true) {
        if (isset($this->_components[$id])) {
            return $this->_components[$id];
        }
        if (isset($this->_definitions[$id])) {
            $definition = $this->_definitions[$id];
            if (is_object($definition) && !$definition instanceof Closure) {
                return $this->_components[$id] = $definition;
            } else {
                return $this->_components[$id] = Mii::create($definition);
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
            unset($this->_components[$id], $this->_definitions[$id]);
            return;
        }
        unset($this->_components[$id]);

        if (is_object($definition) || is_callable($definition, true)) {
            // an object, a class name, or a PHP callable
            $this->_definitions[$id] = $definition;
        } elseif (is_array($definition)) {
            // a configuration array
            if (isset($definition['class'])) {
                $this->_definitions[$id] = $definition;
            } else {
                throw new \Exception("The configuration for the \"$id\" component must contain a \"class\" element.");
            }
        } else {
            throw new \Exception("Unexpected configuration type for the \"$id\" component: " . gettype($definition));
        }
    }

}