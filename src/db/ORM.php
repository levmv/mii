<?php declare(strict_types=1);

namespace mii\db;

use mii\core\Exception;
use mii\log\Log;

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


    /**
     * Create a new ORM model instance
     *
     * @param array $values
     * @param mixed
     * @return void
     */
    public function __construct(?array $values = null, bool $loaded = false)
    {
        $this->__loaded = $loaded;

        if (!\is_null($values)) {
            foreach ($values as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Gets the table name for this object
     * @return string
     */
    public static function table(): string
    {
        $short = static::class;
        $short = (\substr($short, \strrpos($short, '\\') + 1));
        $short = \mb_strtolower(\trim(\preg_replace('/(?<!\p{Lu})\p{Lu}/u', '_\0', $short), '_'));
        if($short[-1] === 's') {
            $short .= 'es';
        } else if($short[-1] === 'y') {
            $short = \substr($short, 0, -1) . 'ies';
        } else {
            $short .= 's';
        }

        return $short;
    }

    /**
     * @return SelectQuery
     */
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
     * @param array $value
     * @return array
     */
    public static function all(array $value = null): array
    {
        if (\is_null($value)) {
            return static::find()->all();
        }

        \assert(!\is_array($value[0]), "This method accepts only array of int/string's");

        return static::find()
            ->where('id', 'IN', $value)
            ->all();
    }


    /**
     * @param array|null $conditions
     * @return Query
     */
    public static function where(...$args): SelectQuery
    {
        if (\count($args) === 1) {
            $conditions = $args[0];

            if (\count($conditions) === 3 && \is_string($conditions[1])) {
                $conditions = [$conditions];
            }

            return static::find()->where($conditions);
        }

        \assert(\count($args) === 3, 'Wrong count of arguments');

        return static::find()->where($args[0], $args[1], $args[2]);
    }


    /**
     * @param int $value
     * @return $this|null
     */
    public static function one(int $value): ?self
    {
        return static::find()
            ->orderBy(null)
            ->where('id', '=', $value)
            ->one();
    }

    /**
     * @param array $conditions
     * @return $this|null
     */
    public static function oneWhere(...$conditions): ?self
    {
        return static::where(...$conditions)->one();
    }


    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param int $id
     * @return static
     */
    public static function oneOrFail(int $id): self
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return static::find()
            ->orderBy(null)
            ->where('id', '=', $id)
            ->oneOrFail();
    }


    /**
     *
     * @return Query
     */
    public static function query(): Query
    {
        return new Query(static::class);
    }


    /**
     * Returns an associative array, where the keys of the array is set to $key
     * column of each row, and the value is set to the $display column.
     *
     * @param string $key the key to use for the array
     * @param string $display the value to use for the display
     * @param string $first first value
     *
     * @return array
     * @deprecated
     */
    public static function selectList($key, $display, $first = null): array
    {
        return static::find()->get()->toList($key, $display, $first);
    }


    public function __set($key, $value)
    {
        // check if its setted by mysqli right now
        if (\is_null($this->__loaded)) {
            $this->attributes[$key] = $value;
            return;
        }

        if (!isset($this->attributes[$key]) || $value !== $this->attributes[$key]) {
            $this->_changed[$key] = true;
        }

        $this->attributes[$key] = $value;
    }

    public function __get($key)
    {
        return $this->attributes[$key];
    }

    public function get(string $key)
    {
        return $this->$key ?? null;
    }

    public function set($values, $value = null): ORM
    {
        if (\is_object($values) && $values instanceof \mii\web\Form) {
            $values = $values->changedFields();
        }

        if (\is_array($values)) {
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
    public function __isset($key)
    {
        return \array_key_exists($key, $this->attributes);
    }


    public function __unset($key)
    {
        if (\array_key_exists($key, $this->attributes)) {
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
     *
     * @param array $properties
     * @return array
     */
    public function toArray(array $properties = []): array
    {
        if (!empty($properties)) {
            $result = [];
            foreach ($properties as $key => $name) {
                if (\is_int($key)) {
                    $result[$name] = $this->$name;
                } elseif (\is_string($name)) {
                    $result[$key] = $this->$name;
                } elseif ($name instanceof \Closure) {
                    $result[$key] = $name($this);
                }
            }

            return $result;
        }

        $result = [];
        foreach ($this as $key => $value) {
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Checks if the field (or any) was changed
     *
     * @param string|array $field_name
     * @return bool
     */

    public function changed($field_name = null): bool
    {
        if ($field_name === null) {
            return \count($this->_changed) > 0;
        }

        if (\is_array($field_name)) {
            return (bool) \count(\array_intersect($field_name, \array_keys($this->_changed)));
        }

        return isset($this->_changed[$field_name]);
    }


    /**
     * Checks if the field (or any) was changed during update/create
     *
     * @param string|array $field_name
     * @return bool
     */

    public function wasChanged($field_name = null): bool
    {
        if ($field_name === null) {
            return \count($this->_was_changed) > 0;
        }

        if (\is_array($field_name)) {
            return (bool) \count(\array_intersect($field_name, \array_keys($this->_was_changed)));
        }

        return isset($this->_was_changed[$field_name]);
    }


    /**
     * Determine if this model is loaded.
     *
     * @return bool
     */
    public function loaded(): bool
    {
        return (bool) $this->__loaded;
    }


    public function refresh()
    {
        $model = self::one($this->id);
        $this->attributes = $model->attributes;
    }

    protected function innerBeforeChange()
    {
    }

    protected function onCreate()
    {
        return true;
    }

    protected function onUpdate()
    {
        return true;
    }

    protected function onChange()
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
     */
    public function create(): int
    {
        $this->innerBeforeChange();

        if ($this->onCreate() === false) {
            return 0;
        }

        $this->onChange();

        static::query()
            ->insert()
            ->columns(\array_keys($this->attributes))
            ->values($this->attributes)
            ->execute();

        $this->__loaded = true;

        $this->attributes['id'] = \Mii::$app->db->insertedId();

        $this->_was_changed = $this->_changed;
        $this->_changed = [];

        $this->onAfterChange();

        return $this->attributes['id'];
    }


    /**
     * Perform update request. Uses value of 'id' attribute as primary key
     *
     * @return int Affected rows
     */
    public function update(): int
    {
        if (!$this->_changed) {
            $this->_was_changed = [];
            return 0;
        }

        $this->innerBeforeChange();

        if ($this->onUpdate() === false) {
            return 0;
        }

        $this->onChange();

        $data = \array_intersect_key($this->attributes, $this->_changed);

        static::query()
            ->update()
            ->set($data)
            ->where('id', '=', $this->attributes['id'])
            ->execute();

        $this->_was_changed = $this->_changed;
        $this->_changed = [];

        $this->onAfterChange();

        return \Mii::$app->db->affectedRows();
    }


    /**
     * Deletes the current object's associated database row.
     * The object will still contain valid data until it is destroyed.
     */
    public function delete(): void
    {
        if ($this->__loaded && isset($this->id)) {
            static::query()
                ->delete()
                ->where('id', '=', (int) $this->id)
                ->execute();

            $this->__loaded = false;

            return;
        }

        throw new Exception('Cannot delete a non-loaded model ' . \get_class($this) . ' or model without id pk!');
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


    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->attributes);
    }
}
