<?php /** @noinspection MagicMethodsValidityInspection */
declare(strict_types=1);

namespace mii\db;

/**
 *
 *  User must add property to his class:
 *  protected array $json_attributes = [];
 *
 *
 * @property array $json_attributes Auto-serialize and unserialize columns on get/set
 */
trait JsonAttributes
{

    protected array $_serialize_cache = [];
    protected bool $_serialize_cache_dirty = false;

    public function __set($key, $value)
    {
        // check if its setted by mysqli right now
        if (\is_null($this->__loaded)) {
            $this->attributes[$key] = $value;
            return;
        }

        if (static::$json_attributes && \in_array($key, static::$json_attributes, true)) {
            $this->_serialize_cache[$key] = $value;
            $this->_serialize_cache_dirty = true;
            return;
        }

        if (!isset($this->attributes[$key]) || $value !== $this->attributes[$key]) {
            $this->_changed[$key] = true;
        }

        $this->attributes[$key] = $value;
    }


    public function __get($key)
    {
        assert(isset(static::$json_attributes), 'You must define property $json_attributes in your model');

        return (static::$json_attributes && \in_array($key, static::$json_attributes, true))
            ? $this->_unserialize_value($key)
            : $this->attributes[$key];
    }


    public function create(): int
    {
        if ($this->_serialize_cache_dirty)
            $this->_invalidateSerializeCache();
        return parent::create();
    }

    public function update(): int
    {
        if ($this->_serialize_cache_dirty) {
            $this->_invalidateSerializeCache();
        }
        return parent::update();
    }

    public function changed($field_name = null): bool
    {
        if ($this->_serialize_cache_dirty) {
            $this->_invalidateSerializeCache();
        }

        return parent::changed($field_name);
    }


    protected function _invalidateSerializeCache(): void
    {
        $this->_serialize_cache_dirty = false;

        foreach ($this->_serialize_cache as $key => $value) {

            $value = \is_null($value)
                ? null
                : $this->_serializeValue($value);

            if (!array_key_exists($key, $this->attributes) || $value !== $this->attributes[$key]) {
                $this->attributes[$key] = $value;
                $this->_changed[$key] = true;
            }
        }
    }

    protected function _serializeValue($value)
    {
        return \json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function _unserialize_value($key)
    {
        if (!\array_key_exists($key, $this->_serialize_cache)) {

            assert(array_key_exists($key, $this->attributes), 'Source field must exist');
            assert(is_string($this->attributes[$key]) || \is_null($this->attributes[$key]), 'Source field must have string type or be null');

            $this->_serialize_cache[$key] = \is_null($this->attributes[$key])
                ? null
                : \json_decode($this->attributes[$key], TRUE);
        }
        return $this->_serialize_cache[$key];
    }


    public function jsonSerialize()
    {
        if ($this->_serialize_cache_dirty) {
            $this->_invalidateSerializeCache();
        }

        return parent::jsonSerialize();
    }

    public function getIterator(): \Traversable
    {
        if ($this->_serialize_cache_dirty) {
            $this->_invalidateSerializeCache();
        }

        return (function () {
            foreach ($this->attributes as $column => $value) {
                yield $column => $this->$column;
            }
        })();
    }
}
