<?php

namespace mii\db;

/**
 * @property array $json_attributes Auto-serialize and unserialize columns on get/set
 */
trait JsonAttributes {

    // protected array $json_attributes = [];

    protected array $_serialize_cache = [];


    public function __set($key, $value)
    {
        // check if its setted by mysqli right now
        if(\is_null($this->__loaded)) {
            $this->attributes[$key] = $value;
            return;
        }

        if ($this->json_attributes && \in_array($key, $this->json_attributes)) {
            $this->_serialize_cache[$key] = $value;
            return;
        }

        if ($this->__loaded === true) {
            if (!isset($this->attributes[$key]) || $value !== $this->attributes[$key]) {
                $this->_changed[$key] = true;
            }
        }
        $this->attributes[$key] = $value;
    }


    public function __get($key)
    {
        assert(isset($this->json_attributes), 'You must define property $json_attributes in your model');

        return ($this->json_attributes !== null && \in_array($key, $this->json_attributes, true))
            ? $this->_unserialize_value($key)
            : $this->attributes[$key];
    }


    public function create()
    {
        if ($this->json_attributes)
            $this->_invalidate_serialize_cache();
        return parent::create();
    }

    public function update()
    {
        if ($this->json_attributes)
            $this->_invalidate_serialize_cache();
        return parent::update();
    }


    protected function _invalidate_serialize_cache(): void
    {
        if (!$this->json_attributes || !$this->_serialize_cache)
            return;

        foreach ($this->json_attributes as $key) {

            $value = isset($this->_serialize_cache[$key])
                ? $this->_serialize_value($this->_serialize_cache[$key])
                : $this->_serialize_value($this->attributes[$key]);

            if ($value !== $this->attributes[$key]) {
                $this->attributes[$key] = $value;

                if ($this->__loaded)
                    $this->_changed[$key] = true;
            }
        }
    }

    protected function _serialize_value($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    protected function _unserialize_value($key)
    {
        if (!\array_key_exists($key, $this->_serialize_cache)) {
            assert(is_string($this->attributes[$key]), 'Source field must have a string value');
            $this->_serialize_cache[$key] = json_decode($this->attributes[$key], TRUE);
        }
        return $this->_serialize_cache[$key];
    }



}