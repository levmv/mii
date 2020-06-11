<?php declare(strict_types=1);

namespace mii\db;

use mii\core\Exception;

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
        $short .= ($short[-1] === 's') ? 'es' : 's';

        return $short;
    }

    /**
     * @return SelectQuery
     */
    public static function find(): SelectQuery
    {
        return static::prepare_query(new SelectQuery(static::class));
    }


    protected static function prepare_query(SelectQuery $query) : SelectQuery
    {
        return $query->order_by(static::$order_by);
    }

    /**
     * @param array $value
     * @return array
     */
    public static function all(array $value = null): array
    {
        if (\is_null($value))
            return static::find()->all();

        assert(!is_array($value[0]), "This method accepts only array of int/string's");

        return static::find()
            ->where('id', 'IN', $value)
            ->all();
    }


    /**
     * @param array|null $conditions
     * @return Query
     */
    public static function where(array $conditions): SelectQuery
    {
        if (count($conditions) === 3 && \is_string($conditions[1])) {
            $conditions = [$conditions];
        }

        return static::find()->where($conditions);
    }


    /**
     * @param int        $value
     * @return $this|null
     */
    public static function one(int $value): ?self
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return static::find()
            ->order_by(null)
            ->where('id', '=', $value)
            ->one();
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param int $id
     * @return static
     */
    public static function one_or_fail(int $id): self
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return static::find()
            ->order_by(null)
            ->where('id', '=', $id)
            ->one_or_fail();
    }


    /**
     *
     * @return Query
     */
    public static function query(): Query
    {
        return (new Query())
            ->table(static::$table)
            ->as_model(static::class);
    }

    /**
     * @param bool             $with_order
     * @param SelectQuery|null $query
     * @return SelectQuery
     */
    public static function select_query(bool $with_order = true, SelectQuery $query = null): SelectQuery
    {
        if ($query === null) {
            $query = new SelectQuery;
        }

        $query
            ->select(['*'], true)
            ->as_model(static::class);

        if ($with_order && !empty(static::$order_by)) {
            foreach (static::$order_by as $column => $direction) {
                $query->order_by($column, $direction);
            }
        }

        return $query;
    }

    public function raw_query(): Query
    {
        return (new Query)->as_model(static::class);
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
    public static function select_list($key, $display, $first = NULL)
    {
        return static::find()->get()->to_list($key, $display, $first);
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

    public function set($values, $value = NULL): ORM
    {
        if (\is_object($values) and $values instanceof \mii\web\Form) {

            $values = $values->changed_fields();

        } elseif (!\is_array($values)) {
            $values = [$values => $value];
        }

        foreach ($values as $key => $val) {
            $this->$key = $val;
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
            '__loaded'
        ];
    }


    /**
     * Gets an array version of the model
     *
     * @param array $properties
     * @return array
     */
    public function to_array(array $properties = []): array
    {
        if (!empty($properties)) {
            $result = [];
            foreach ($properties as $key => $name) {
                if (\is_int($key)) {
                    $result[$name] = $this->$name;
                } else {
                    if (\is_string($name)) {
                        $result[$key] = $this->$name;
                    } elseif ($name instanceof \Closure) {
                        $result[$key] = $name($this);
                    }
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
            return (bool)\count(\array_intersect($field_name, \array_keys($this->_changed)));
        }

        return isset($this->_changed[$field_name]);
    }


    /**
     * Checks if the field (or any) was changed during update/create
     *
     * @param string|array $field_name
     * @return bool
     */

    public function was_changed($field_name = null): bool
    {
        if ($field_name === null) {
            return \count($this->_was_changed) > 0;
        }

        if (\is_array($field_name)) {
            return (bool)\count(\array_intersect($field_name, \array_keys($this->_was_changed)));
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
        return (bool)$this->__loaded;
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

        if ($this->on_update() === false)
            return 0;

        $this->on_change();

        $data = \array_intersect_key($this->attributes, $this->_changed);

        static::query()
            ->update()
            ->set($data)
            ->where('id', '=', $this->attributes['id'])
            ->execute();

        $this->on_after_change();

        $this->_was_changed = $this->_changed;
        $this->_changed = [];

        return \Mii::$app->db->affected_rows();
    }


    public function refresh()
    {
        $model = self::one($this->id);
        $this->attributes = $model->attributes;
    }


    protected function on_create()
    {
        return true;
    }

    protected function on_update()
    {
        return true;
    }

    protected function on_change()
    {
    }

    /**
     * @deprecated
     */
    protected function on_after_change(): void
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
        if ($this->on_create() === false) {
            return 0;
        }

        $this->on_change();

        static::query()
            ->insert()
            ->columns(\array_keys($this->attributes))
            ->values($this->attributes)
            ->execute();

        $this->__loaded = true;

        $this->attributes['id'] = \Mii::$app->db->inserted_id();

        $this->on_after_change();

        $this->_was_changed = $this->_changed;
        $this->_changed = [];

        return $this->attributes['id'];
    }


    /**
     * Deletes the current object's associated database row.
     * The object will still contain valid data until it is destroyed.
     */
    public function delete(): void
    {
        if ($this->__loaded && isset($this->id)) {
            $this->__loaded = false;

            $this->raw_query()
                ->delete(static::$table)
                ->where('id', '=', (int)$this->id)
                ->execute();

            return;
        }

        throw new Exception('Cannot delete a non-loaded model ' . \get_class($this) . ' or model without id pk!');
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize()
    {
        return $this->to_array();
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->attributes);
    }
}
