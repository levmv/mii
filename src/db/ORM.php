<?php declare(strict_types=1);

namespace mii\db;

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
     * @param array|null $values
     * @param bool $loaded
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
        if (\count($args) === 1) {
            $conditions = $args[0];

            if (\count($conditions) === 3 && \is_string($conditions[1])) {
                $conditions = [$conditions];
            }

            return static::find()->whereAll($conditions);
        }

        \assert(\count($args) === 3, 'Wrong count of arguments');

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
     * @param string $key the key to use for the array
     * @param string $display the value to use for the display
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
        if (\is_null($this->__loaded)) {
            $this->attributes[$key] = $value;
            return;
        }

        if (!\array_key_exists($key, $this->attributes) || $value !== $this->attributes[$key]) {
            $this->_changed[$key] = true;
            $this->attributes[$key] = $value;
        }
    }

    public function __get($key)
    {
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
    public function __isset(string $key)
    {
        return \array_key_exists($key, $this->attributes);
    }

    public function has(string $key): bool
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
     */
    public function toArray(array $properties = []): array
    {
        $result = [];
        if (!empty($properties)) {
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
        if ($fieldName === null) {
            return \count($this->_changed) > 0;
        }

        if (\is_array($fieldName)) {
            return (bool)\count(\array_intersect($fieldName, \array_keys($this->_changed)));
        }

        return isset($this->_changed[$fieldName]);
    }


    /**
     * Checks if the field (or any) was changed during update/create
     */
    public function wasChanged(array|string $fieldName = null): bool
    {
        if ($fieldName === null) {
            return \count($this->_was_changed) > 0;
        }

        if (\is_array($fieldName)) {
            return (bool)\count(\array_intersect($fieldName, \array_keys($this->_was_changed)));
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
        $this->innerBeforeChange();
        $this->onCreate();
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
     * @throws DatabaseException
     */
    public function update(): int
    {
        if (!$this->_changed) {
            $this->_was_changed = [];
            return 0;
        }

        $this->innerBeforeChange();
        $this->onUpdate();
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


    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->attributes);
    }
}
