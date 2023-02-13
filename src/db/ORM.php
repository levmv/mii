<?php declare(strict_types=1);

namespace mii\db;

use mii\log\Log;
use function array_key_exists;
use function assert;
use function count;
use function enum_exists;
use function is_array;
use function is_int;
use function is_null;
use function is_string;
use function json_decode;
use function str_replace;
use const JSON_THROW_ON_ERROR;

class ORM implements \JsonSerializable, \IteratorAggregate
{
    /**
     * @var mixed
     */
    public static array $order_by = [];

    /**
     * @var array The model attributes
     */
    protected array $attributes = [];

    /**
     * @var  array  Data that's changed since the object was loaded
     */
    protected array $_changed = [];

    /**
     * @var  array  Data that's changed during update/create
     */
    protected array $_was_changed = [];

    /**
     * @var boolean Is this model loaded from DB
     */
    public ?bool $__loaded = null;

    protected array $casts = [];

    protected array $_serializeCache = [];
    protected bool $_serializeCacheDirty = false;


    /**
     * Create a new ORM model instance
     *
     * @param array|null $values
     * @param bool       $loaded
     */
    public function __construct(?array $values = null, bool $loaded = false)
    {
        $this->__loaded = $loaded;

        if (!is_null($values)) {
            foreach ($values as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Gets the table name for this object
     */
    public static function table(): string
    {
        $short = static::class;
        $short = (\substr($short, \strrpos($short, '\\') + 1));
        $short = \mb_strtolower(\trim(\preg_replace('/(?<!\p{Lu})\p{Lu}/u', '_\0', $short), '_'));
        if ($short[-1] === 's') {
            $short .= 'es';
        } else if ($short[-1] === 'y') {
            $short = \substr($short, 0, -1) . 'ies';
        } else {
            $short .= 's';
        }

        return $short;
    }

    public static function find(): SelectQuery
    {
        return static::prepareQuery(new SelectQuery(static::class));
    }


    protected static function prepareQuery(SelectQuery $query): SelectQuery
    {
        if (!empty(static::$order_by)) {
            foreach (static::$order_by as $col => $dir) {
                $query->orderBy($col, $dir);
            }
        }

        return $query;
    }

    /**
     * @param array $args
     * @return static[]
     */
    public static function all(...$args): array
    {
        if (empty($args)) {
            return static::find()->all();
        }

        return static::where(...$args)->all();
    }


    /**
     * @param array $args
     */
    public static function where(...$args): SelectQuery
    {
        if (count($args) === 1) {
            $conditions = $args[0];

            if (count($conditions) === 3 && is_string($conditions[1])) {
                $conditions = [$conditions];
            }

            return static::find()->whereAll($conditions);
        }

        assert(count($args) === 3, 'Wrong count of arguments');

        return static::find()->where($args[0], $args[1], $args[2]);
    }


    public static function one(int $value): ?static
    {
        return static::find()
            ->orderBy(null)
            ->where('id', '=', $value)
            ->one();
    }

    /**
     * @param array $conditions
     */
    public static function oneWhere(...$conditions): ?static
    {
        return static::where(...$conditions)->one();
    }


    public static function oneOrFail(int $id): static
    {
        return static::find()
            ->orderBy(null)
            ->where('id', '=', $id)
            ->oneOrFail();
    }


    public static function query(): Query
    {
        return new Query(static::class);
    }


    /**
     * Returns an associative array, where the keys of the array is set to $key
     * column of each row, and the value is set to the $display column.
     *
     * @param string            $key the key to use for the array
     * @param string            $display the value to use for the display
     * @param array|string|null $first first value
     * @return array
     */
    public static function selectList(string $key, string $display, array|string $first = null): array
    {
        return static::find()->select($key, $display)->get()->toList($key, $display, $first);
    }


    public function __set($key, $value)
    {
        // check if its setted by mysqli right now
        if (is_null($this->__loaded)) {
            $this->attributes[$key] = $value;
            return;
        }

        if (isset($this->casts[$key])) {
            if ($this->casts[$key] === 'array') {
                $this->_serializeCache[$key] = $value;
                $this->_serializeCacheDirty = true;
                return;
            }
            $value = $this->_writeCast($key, $value);
        }

        if (!array_key_exists($key, $this->attributes) || $value !== $this->attributes[$key]) {
            $this->_changed[$key] = true;
            $this->attributes[$key] = $value;
        }
    }

    public function __get($key)
    {
        if (isset($this->casts[$key])) {
            return $this->_readCast($key);
        }

        return $this->attributes[$key];
    }

    public function get(string $key)
    {
        return $this->$key ?? null;
    }

    public function set($values, $value = null): static
    {
        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        if ($values instanceof \mii\web\Form) {
            $values = $values->changedFields();
        }

        if (is_array($values)) {
            foreach ($values as $key => $val) {
                $this->$key = $val;
            }
        } else {
            $this->$values = $value;
        }

        return $this;
    }

    /**
     * @param string $key the property to test
     * @return bool
     */
    public function __isset(string $key)
    {
        return array_key_exists($key, $this->attributes);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }


    public function __unset($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            unset($this->attributes[$key]);

            if (isset($this->_changed[$key])) {
                unset($this->_changed[$key]);
            }
        }
    }


    public function __sleep()
    {
        return [
            'attributes',
            '__loaded',
        ];
    }


    /**
     * Gets an array version of the model
     */
    public function toArray(array $properties = []): array
    {
        $result = [];
        if (!empty($properties)) {
            foreach ($properties as $key => $name) {
                if (is_int($key)) {
                    $result[$name] = $this->$name;
                } elseif (is_string($name)) {
                    $result[$key] = $this->$name;
                } elseif ($name instanceof \Closure) {
                    $result[$key] = $name($this);
                }
            }

            return $result;
        }

        foreach ($this as $key => $value) {
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Checks if the field (or any) was changed
     */
    public function changed(array|string $fieldName = null): bool
    {
        if ($this->_serializeCacheDirty) {
            $this->_invalidateSerializeCache();
        }

        if ($fieldName === null) {
            return count($this->_changed) > 0;
        }

        if (is_array($fieldName)) {
            return (bool)count(\array_intersect($fieldName, \array_keys($this->_changed)));
        }

        return isset($this->_changed[$fieldName]);
    }


    /**
     * Checks if the field (or any) was changed during update/create
     */
    public function wasChanged(array|string $fieldName = null): bool
    {
        if ($fieldName === null) {
            return count($this->_was_changed) > 0;
        }

        if (is_array($fieldName)) {
            return (bool)count(\array_intersect($fieldName, \array_keys($this->_was_changed)));
        }

        return isset($this->_was_changed[$fieldName]);
    }


    /**
     * Determine if this model is loaded.
     */
    public function loaded(): bool
    {
        return (bool)$this->__loaded;
    }


    public function refresh(): void
    {
        $model = self::one($this->get('id'));
        $this->attributes = $model->attributes;
    }

    protected function innerBeforeChange(): void
    {
    }

    protected function onCreate(): void
    {
    }

    protected function onUpdate(): void
    {
    }

    protected function onChange(): void
    {
    }


    protected function onAfterChange(): void
    {
    }


    /**
     * Saves the model to your database. It will do a
     * database INSERT and assign the inserted row id to $data['id'].
     *
     * @return int Inserted row id
     * @throws DatabaseException
     */
    public function create(): int
    {
        if ($this->_serializeCacheDirty) {
            $this->_invalidateSerializeCache();
        }
        $this->innerBeforeChange();
        $this->onCreate();
        $this->onChange();

        $this->attributes['id'] = static::query()
            ->insert()
            ->columns(\array_keys($this->attributes))
            ->values($this->attributes)
            ->execute();

        $this->__loaded = true;

        $this->_was_changed = $this->_changed;
        $this->_changed = [];

        $this->onAfterChange();

        return $this->attributes['id'];
    }


    /**
     * Perform update request. Uses value of 'id' attribute as primary key
     *
     * @return int Affected rows
     * @throws DatabaseException
     */
    public function update(): int
    {
        if ($this->_serializeCacheDirty) {
            $this->_invalidateSerializeCache();
        }

        if (!$this->_changed) {
            $this->_was_changed = [];
            return 0;
        }

        $this->innerBeforeChange();
        $this->onUpdate();
        $this->onChange();

        $data = \array_intersect_key($this->attributes, $this->_changed);

        $affectedRows = static::query()
            ->update()
            ->set($data)
            ->where('id', '=', $this->attributes['id'])
            ->execute();

        $this->_was_changed = $this->_changed;
        $this->_changed = [];

        $this->onAfterChange();

        return $affectedRows;
    }


    /**
     * Deletes the current object's associated database row.
     * The object will still contain valid data until it is destroyed.
     */
    public function delete(): void
    {
        $this->innerDelete();
    }

    protected function innerDelete(): void
    {
        if ($this->__loaded && isset($this->id)) {
            static::query()
                ->delete()
                ->where('id', '=', (int)$this->id)
                ->execute();

            $this->__loaded = false;

            return;
        }

        throw new \RuntimeException('Cannot delete a non-loaded model ' . static::class . ' or model without id pk!');
    }


    public function deleteWith(callable $callback): bool
    {
        DB::begin();
        try {
            $callback($this);
            $this->delete();
            DB::commit();
        } catch (\Throwable $t) {
            DB::rollback();
            Log::error($t);
            return false;
        }

        return true;
    }

    protected function _invalidateSerializeCache(): void
    {
        $this->_serializeCacheDirty = false;

        foreach ($this->_serializeCache as $key => $value) {
            $value = is_null($value)
                ? null
                : $this->_serializeValue($value);

            if (!array_key_exists($key, $this->attributes) || $value !== $this->attributes[$key]) {
                $this->attributes[$key] = $value;
                $this->_changed[$key] = true;
            }
        }
    }

    /**
     * @throws \JsonException
     */
    protected function _serializeValue($value): bool|string
    {
        return \json_encode($value, JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    protected function _unserializeValue($key)
    {
        if (!array_key_exists($key, $this->_serializeCache)) {
            assert(array_key_exists($key, $this->attributes), 'Source field must exist');
            assert(is_string($this->attributes[$key]) || is_null($this->attributes[$key]), 'Source field must have string type or be null');

            $this->_serializeCache[$key] = is_null($this->attributes[$key])
                ? null
                : json_decode($this->attributes[$key], true, 512, JSON_THROW_ON_ERROR);
        }
        return $this->_serializeCache[$key];
    }


    protected function _readCast(string $key): mixed
    {
        $type = $this->casts[$key];

        switch ($type) {
            case 'int':
            case 'float':
                return $this->attributes[$key];
            case 'array':
                return $this->_unserializeValue($key);
            case 'bool':
                return is_null($this->attributes[$key]) ? null : (bool)$this->attributes[$key];
        }

        assert(enum_exists($type));
        return $type::tryFrom($this->attributes[$key]);
    }

    protected function _writeCast(string $key, mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        switch ($this->casts[$key]) {
            case 'int':
                return (int)$value;
            case 'float':
                return (is_string($value) ? (float)str_replace(',', '.', $value) : (float)$value);
            case 'bool':
                return (bool)$value;
        }

        assert($value instanceof \BackedEnum);
        return $value->value;
    }


    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize(): mixed
    {
        if ($this->_serializeCacheDirty) {
            $this->_invalidateSerializeCache();
        }
        return $this->toArray();
    }

    public function getIterator(): \Traversable
    {
        if ($this->_serializeCacheDirty) {
            $this->_invalidateSerializeCache();
        }
        return new \ArrayIterator($this->attributes);
    }
}
