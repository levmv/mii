<?php

namespace mii\db;


class ORM
{

    // The database table name
    protected $table = '';

    /**
     * @var mixed
     */
    protected $_order_by = false;

    // The database fields
    protected $_data = [];

    /**
     * @var  array  Data that's changed since the object was loaded
     */
    protected $_changed = [];

    protected $_loaded;

    /**
     * Create a new ORM model instance
     *
     * @param array $values
     * @return void
     */
    public function __construct($values = [], $loaded = NULL)
    {
        if ($values)
            $this->fill_with($values);

        $this->_loaded = $loaded;
    }

    /**
     * Mass sets object properties. Never pass $_POST into this method directly.
     * Always use something like array_key_intersect() to filter the array.
     *
     * @param array $data the data to set
     *
     * @return null
     *
     */
    public function fill_with(array $data)
    {
        foreach (array_intersect_key($data, $this->_data) as $key => $value) {
            $this->$key = $value;
        }

        return $this;
    }

    /**
     * @param null $id
     * @return Query
     */
    public static function find($id = null)
    {
        $class = new static();

        if ($id) {
            if(is_array($id)) {

                return $class->query()->where('id', 'IN', DB::expr('('.implode(',', $id).')'))->get();

            } else {
                return $class->query(false)->where('id', '=', $id)->one();
            }
        }

        return $class->query();
    }

    /**
     * @param bool $with_order
     * @return Query
     */
    public function query($with_order = true)
    {
        $query = (new Query)->select($this->fields())->from($this->get_table())->as_object(static::class);

        if ($this->_order_by AND $with_order) {
            foreach ($this->_order_by as $column => $direction) {
                $query->order_by($column, $direction);
            }
        }

        return $query;
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
        return $this->table;
    }

    public static function all()
    {
        $class = new static();

        return $class->query()->get();
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
    public static function select_list($key, $display, $first = NULL, Database_Query_Builder_Select $query = NULL)
    {
        $instance = new static;
        $rows = [];


        if ($first) {
            if (is_array($first)) {
                $rows = $first;
            } else {
                $rows[0] = $first;
            }

        }

        $array_display = false;
        $select_array = [$key];
        if (is_array($display)) {
            $array_display = true;
            $select_array = array_merge($select_array, $display);
        } else {
            $select_array[] = $display;
        }

        if ($query) // Fetch selected rows
        {
            $query = $instance->load($query->select_array($select_array), NULL);
        } else // Fetch all rows
        {
            $query = (new Query)->select($instance->fields())->from($instance->get_table())->as_object(static::class)->all();
        }


        foreach ($query as $row) {
            if ($array_display) {
                $display_str = [];
                foreach ($display as $text)
                    $display_str[] = $row->$text;
                $rows[$row->$key] = implode(' - ', $display_str);
            } else {
                $rows[$row->$key] = $row->$display;
            }
        }

        return $rows;
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    public function __set($key, $value)
    {
        return $this->set_field($key, $value);
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
     * @return $this
     */
    public function set_field($key, $value)
    {
        if (array_key_exists($key, $this->_data) AND $value !== $this->_data[$key]) {
            $this->_data[$key] = $value;
            if ($this->_loaded !== NULL) {
                $this->_changed[$key] = true;
            }
        }

        return $this;
    }

    public function set($values, $value = NULL)
    {

        if (!is_array($values)) {
            $this->set_field($values, $value);
        } else {
            foreach ($values as $key => $value) {
                $this->set_field($key, $value);
            }
        }

        return $this;
    }

    /**
     * sleep method for serialization
     *
     * @return array
     */
    public function __sleep()
    {
        // Store only information about the object without db property
        return array_diff(array_keys(get_object_vars($this)), ['_db']);
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
     * Checks if the field (or any) was changed
     *
     * @param string $field_name
     * @return bool
     */

    public function changed($field_name = null)
    {
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
     * @return int
     */
    public function update($validation = NULL)
    {
        if (!count($this->_changed))
            return 0;

        if ($this->on_update() === false)
            return $this;

        return (new Query)
            ->update($this->get_table())
            ->set(array_intersect_key($this->_data, $this->_changed))
            ->where('id', '=', $this->_data['id'])
            ->execute();
    }

    public function on_update()
    {
        return true;
    }

    /**
     * Saves the model to your database. It will do a
     * database INSERT and assign the inserted row id to $data['id'].
     *
     * @param mixed $validation a manual validation object to combine the model properties with
     *
     * @return int
     */
    public function create($validation = NULL)
    {
        if ($this->on_create() === false) {
            return $this;
        }

        $columns = array_keys($this->_data);
        $id = (new Query())
            ->insert($this->get_table())
            ->columns($columns)
            ->values($this->_data)
            ->execute();

        $this->_loaded = true;

        $this->_data['id'] = $id[0];

        return $this;

    }

    public function on_create()
    {
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

            return (new Query)
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
