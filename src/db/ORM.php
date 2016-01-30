<?php

namespace mii\db;


class ORM implements ORMInterface
{

    /**
     * @var string database table name
     */
    protected static $table;

    /**
     * @var mixed
     */
    protected $_order_by = false;

    /**
     * @var array The database fields
     */
    protected $_data = [];

    /**
     * @var  array  Data that's changed since the object was loaded
     */
    protected $_changed = [];

    /**
     * @var boolean Is this model loaded from DB
     */
    protected $_loaded = false;


    /**
     * Create a new ORM model instance
     *
     * @param array $values
     * @param mixed
     * @return void
     */
    public function __construct($values = [], $loaded = false)
    {
        if ($values) {
            foreach (array_intersect_key($values, $this->_data) as $key => $value) {
                $this->$key = $value;
            }
        }

        $this->_loaded = $loaded;
    }


    /**
     * @param mixed ID of model to load or set of ids
     * @return Query|$this
     */
    public static function find($id = null)
    {
        if ($id)
            return static::find_by_id($id);

        return (new static)->select_query();
    }


    /**
     * @param $id
     * @return $this
     */
    public static function find_by_id($id)
    {
        $class = new static();

        if(is_array($id)) {
            return $class->select_query()->where('id', 'IN', DB::expr('('.implode(',', $id).')'))->get();
        } else {
            return $class->select_query(false)->where('id', '=', $id)->one();
        }
    }

    /**
     *
     * @return Query
     */
    public static function query()
    {
        return (new Query)->table(static::$table)->as_object(static::class);
    }

    /**
     * @param bool $with_order
     * @return Query
     */
    public function select_query($with_order = true)
    {
        $query = $this->query_object()->select($this->fields())->from($this->get_table())->as_object(static::class);

        if ($this->_order_by AND $with_order) {
            foreach ($this->_order_by as $column => $direction) {
                $query->order_by($column, $direction);
            }
        }

        return $query;
    }


    public function query_object() {
        return new Query;
    }


    /**
     * @return \mii\db\Result
     */
    public static function all()
    {
        return static::find()->get();
    }

    /**
     * Returns an associative array, where the keys of the array is set to $key
     * column of each row, and the value is set to the $display column.
     * You can optionally specify the $query parameter to pass to filter for
     * different data.
     *
     * @param array $key the key to use for the array
     * @param array $where the value to use for the display
     * @param array $where the where clause
     *
     * @return Result
     */
    public static function select_list($key, $display, $first = NULL)
    {
        $class = new static();

        $query = $class->query_object()
            ->select($class->fields())
            ->from($class->get_table())
            ->as_array();

        if ($class->_order_by) {
            foreach ($class->_order_by as $column => $direction) {
                $query->order_by($column, $direction);
            }
        }

        return $query->get()->to_list($key, $display, $first);
    }



    public function __get($key)
    {
        return $this->get($key);
    }

    public function __set($key, $value)
    {
        $this->set_field($key, $value);
    }

    /**
     * Retrieve items from the $data array.
     *
     *    <h1><?=$blog_entry->title?></h1>
     *    <p><?=$blog_entry->content?></p>
     *
     * @param string $key the field name to look for
     *
     * @throws ORMException
     *
     * @return String
     */
    public function get($key)
    {
        if (isset($this->_data[$key]) OR array_key_exists($key, $this->_data))
            return $this->_data[$key];

        throw new ORMException('Field ' . $key . ' does not exist in ' . get_class($this) . '!', [], '');
    }

    /**
     * Set the items in the $data array.
     *
     * @param string $key the field name to set
     * @param string $value the value to set to
     *
     * @return void
     */
    public function set_field($key, $value)
    {
        if ((isset($this->_data[$key]) OR array_key_exists($key, $this->_data)) AND $value !== $this->_data[$key]) {
            $this->_data[$key] = $value;
            if ($this->_loaded !== false) {
                $this->_changed[$key] = true;
            }
        }
    }

    public function set($values, $value = NULL)
    {
        if(is_object($values) AND $values instanceof \mii\web\Form) {
            $values = $values->changed_fields();
            foreach ($values as $key => $value) {
                $this->set_field($key, $value);
            }
        } elseif (!is_array($values)) {
            $this->set_field($values, $value);
        } else {
            foreach ($values as $key => $value) {
                $this->set_field($key, $value);
            }
        }

        return $this;
    }

    /**
     * Magic isset method to test _data
     *
     * @param string $name the property to test
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->_data[$name]);
    }

    /**
     * Gets an array version of the model
     *
     * @return array
     */
    public function as_array()
    {
        return $this->_data;
    }


    /**
     * Returns an array of the columns in this object.
     *
     * @return array
     */
    public function fields()
    {
        $fields = [];

        foreach ($this->_data as $key => $value)
            $fields[] = $this->get_table() . '.' . $key;

        return $fields;
    }

    /**
     * Gets the table name for this object
     *
     * @return string
     */
    public function get_table()
    {
        return static::$table;
    }


    /**
     * Checks if the field (or any) was changed
     *
     * @param string $field_name
     * @return bool
     */

    public function changed($field_name = null)
    {
        // For not loaded models there is no way to detect changes.
        if(!$this->loaded())
            return true;

        if ($field_name === null) {
            return count($this->_changed) > 0;
        }

        return isset($this->_changed[$field_name]);
    }

    /**
     * Saves the model to your database.
     *
     * @param mixed $validation a manual validation object to combine the model properties with
     *
     * @return int Affected rows
     */
    public function update($validation = NULL)
    {
        if (! (bool) $this->_changed)
            return 0;

        if ($this->on_update() === false)
            return 0;

        $this->on_change();

        return $this->query_object()
            ->update($this->get_table())
            ->set(array_intersect_key($this->_data, $this->_changed))
            ->where('id', '=', $this->_data['id'])
            ->execute();
    }

    protected function on_update()
    {
        return true;
    }

    /**
     * Saves the model to your database. It will do a
     * database INSERT and assign the inserted row id to $data['id'].
     *
     * @param mixed $validation a manual validation object to combine the model properties with
     *
     * @return int Inserted row id
     */
    public function create($validation = NULL)
    {
        if ($this->on_create() === false) {
            return 0;
        }

        $this->on_change();

        $columns = array_keys($this->_data);
        $id = $this->query_object()
            ->insert($this->get_table())
            ->columns($columns)
            ->values($this->_data)
            ->execute();

        $this->_loaded = true;

        $this->_data['id'] = $id[0];

        return $id[0];
    }

    protected function on_create()
    {
        return true;

    }

    protected function on_change() {
        return true;
    }

    /**
     * Deletes the current object's associated database row.
     * The object will still contain valid data until it is destroyed.
     *
     * @return integer
     */
    public function delete()
    {
        if ($this->loaded()) {
            $this->_loaded = false;

            return $this->query_object()
                ->delete($this->get_table())
                ->where('id', '=', $this->_data['id'])
                ->execute();
        }

        throw new ORMException('Cannot delete a non-loaded model ' . get_class($this) . '!', [], []);
    }

    /**
     * Determine if this model is loaded.
     *
     * @return bool
     */
    public function loaded()
    {
        return (bool)$this->_loaded;
    }

    /**
     * Returns if the specified field exists in the model
     *
     * @return bool
     */
    public function field_exists($field)
    {
        return array_key_exists($field, $this->_data);
    }

}
