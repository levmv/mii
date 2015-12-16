<?php

namespace mii\core;

use ReflectionClass;

class Container {

    private $_definitions = [];

    private $_reflections = [];

    private $_dependencies = [];

    public function get($name, $config = []) {

        if(!isset($this->_definitions[$name])) {

            return $this->build($name, $config, []);
        }

        $definition = $this->_definitions[$name];

        if (is_callable($definition, true)) {
            //$params = $this->resolveDependencies($this->mergeParams($class, $params));
            $object = call_user_func($definition, $this, $config);

        } elseif (is_array($definition)) {

            $concrete = $definition['class'];
            unset($definition['class']);

            $config = array_merge($definition, $config);

            $object = $this->get($concrete, $config);

        } elseif (is_object($definition)) {

            //return $this->_singletons[$class] = $definition;

        } else {
            throw new \InvalidConfigException('Unexpected object definition type: ' . gettype($definition));
        }

        return $object;
    }

    public function set($name, $definition) {
        $this->_definitions[$name] = $definition;
        return $this;
    }

    public function has($name) {
        return isset($this->_definitions[$name]);
    }

    protected function build($class, $params, $config)
    {
        /* @var $reflection ReflectionClass */
        list ($reflection, $dependencies) = $this->getDependencies($class);
        foreach ($params as $index => $param) {
            $dependencies[$index] = $param;
        }
        $dependencies = $this->resolveDependencies($dependencies, $reflection);
        if (empty($config)) {
            return $reflection->newInstanceArgs($dependencies);
        }
        if (!empty($dependencies) && $reflection->implementsInterface('yii\base\Configurable')) {
            // set $config as the last parameter (existing one will be overwritten)
            $dependencies[count($dependencies) - 1] = $config;
            return $reflection->newInstanceArgs($dependencies);
        } else {
            $object = $reflection->newInstanceArgs($dependencies);
            foreach ($config as $name => $value) {
                $object->$name = $value;
            }
            return $object;
        }
    }

    protected function getDependencies($class)
    {
        if (isset($this->_reflections[$class])) {
            return [$this->_reflections[$class], $this->_dependencies[$class]];
        }
        $dependencies = [];
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    $c = $param->getClass();
                    $dependencies[] = Instance::of($c === null ? null : $c->getName());
                }
            }
        }
        $this->_reflections[$class] = $reflection;
        $this->_dependencies[$class] = $dependencies;
        return [$reflection, $dependencies];
    }

    protected function resolveDependencies($dependencies, $reflection = null)
    {
        foreach ($dependencies as $index => $dependency) {
            if ($dependency instanceof Instance) {
                if ($dependency->id !== null) {
                    $dependencies[$index] = $this->get($dependency->id);
                } elseif ($reflection !== null) {
                    $name = $reflection->getConstructor()->getParameters()[$index]->getName();
                    $class = $reflection->getName();
                    throw new InvalidConfigException("Missing required parameter \"$name\" when instantiating \"$class\".");
                }
            }
        }
        return $dependencies;
    }



}