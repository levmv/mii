<?php

namespace mii\db;


use mii\util\Arr;

class ORM
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
     * @var array Auto-serialize and unserialize columns on get/set
     */
    protected $_serialize_fields;


    protected $_serialize_cache = [];

    /**
     * @var  array  Unmapped data that is still accessible
     */
    protected $_unmapped = [];

    /**
     * @var  array  Data that's changed since the object was loaded
     */
    protected $_changed = [];


    protected $_exclude_fields = [];

    /**
     * @var boolean Is this model loaded from DB
     */
    public $__loaded = false;


    /**
     * Create a new ORM model instance
     *
     * @param array $values
     * @param mixed
     * @return void
     */
    public function __construct($values = [], $loaded = false) {

        if ($values) {
            foreach (array_intersect_key($values, $this->_data) as $key => $value) {
                $this->$key = $value;
            }
        }
        $this->__loaded = $loaded;
    }

    /**
     *
     * @return Query
     */
    public static function query() {
        return (new static)
            ->raw_query()
            ->table(static::$table)
            ->as_object(static::class, [[], true]);
    }

    /**
     * @return \mii\db\Result
     */
    public static function all($value = null): array {
        if ($value !== null) {

            assert(is_array($value) === true, 'Value must be an array or null');

            if (!is_array($value[0])) {
                return (new static)
                    ->select_query()
                    ->where('id', 'IN', $value)
                    ->all();
            }

            assert(count($value[0]) === 3, "Wrong conditions array");

            return (new static)
                ->select_query(true)
                ->where($value)
                ->all();
        }

        return static::find()->all();
    }


    /**
     * @return Query
     */
    public static function find() {
        return (new static)->select_query();
    }

    /**
     * @param mixed $id
     * @return $this|null
     */

    public static function one($value = null, $find_or_fail = false) {
        $result = null;
        if (is_array($value)) {

            assert(count($value[0]) === 3, "Wrong conditions array");

            $result =  (new static)
                ->select_query(false)
                ->where($value)
                ->one();

        } elseif (is_integer($value) || is_string($value)) {
            $result =  (new static)->select_query(false)->where('id', '=', (int)$value)->one();
        } else {
            $result = (new static)->select_query(true)->one();
        }

        if($find_or_fail && $result === null)
            throw new ModelNotFoundException;

        return $result;
    }


    /**
     * @param bool $with_order
     * @return Query
     */
    public function select_query($with_order = true, Query $query = null): Query {

        if ($query === null)
            $query = new Query;

        $query->select($this->fields(), true)->from($this->get_table())->as_object(static::class, [[], true]);

        if ($this->_order_by AND $with_order) {
            foreach ($this->_order_by as $column => $direction) {
                $query->order_by($column, $direction);
            }
        }

        return $query;
    }

    public function raw_query(): Query {
        return new Query;
    }

    /**
     * Returns an array of the columns in this object.
     *
     * @return array
     */
    public function fields(): array {
        $fields = [];

        $table = $this->get_table();

        foreach ($this->_data as $key => $value) {
            if (!in_array($key, $this->_exclude_fields)) {
                $fields[] = "`$table`.`$key`"; // TODO: support for table prefixes
            }
        }

        return $fields;
    }

    /**
     * Gets the table name for this object
     *
     * @return string
     */
    public function get_table(): string {
        return static::$table;
    }

    /**
     * Returns an associative array, where the keys of the array is set to $key
     * column of each row, and the value is set to the $display column.
     *
     * @param string $key the key to use for the array
     * @param string $display the value to use for the display
     * @param string $first first value
     *
     * @return Result
     */
    public static function select_list($key, $display, $first = NULL) {
        $class = new static();

        $query = $class->raw_query()
            ->select([static::$table . '.' . $key, static::$table . '.' . $display])
            ->from($class->get_table())
            ->as_array();

        if ($class->_order_by) {
            foreach ($class->_order_by as $column => $direction) {
                $query->order_by($column, $direction);
            }
        }

        return $query->get()->to_list($key, $display, $first);
    }


    public function __set($key, $value) {
        if (isset($this->_data[$key]) OR array_key_exists($key, $this->_data)) {

            if ($this->__loaded !== false) {
                if ($value !== $this->_data[$key]) {
                    $this->_changed[$key] = true;
                }

                if ($this->_serialize_fields !== null && in_array($key, $this->_serialize_fields)) {
                    $this->_serialize_cache[$key] = $value;
                } else {
                    $this->_data[$key] = $value;
                }

            } else {
                $this->_data[$key] = $value;
            }

        } else {
            $this->_unmapped[$key] = $value;
        }
    }

    /**
     * MUST BE EQUAL TO ::GET()
     */
    public function __get($key) {
        if (isset($this->_data[$key]) OR array_key_exists($key, $this->_data)) {

            return ($this->_serialize_fields !== null && $this->__loaded && in_array($key, $this->_serialize_fields, true))
                ? $this->_unserialize_value($key)
                : $this->_data[$key];
        }

        if (array_key_exists($key, $this->_unmapped))
            return $this->_unmapped[$key];

        throw new ORMException('Field ' . $key . ' does not exist in ' . get_class($this) . '!', [], '');
    }

    public function get(string $key) {
        if (isset($this->_data[$key]) OR array_key_exists($key, $this->_data)) {

            return ($this->_serialize_fields !== null && $this->__loaded && in_array($key, $this->_serialize_fields, true))
                ? $this->_unserialize_value($key)
                : $this->_data[$key];
        }

        if (array_key_exists($key, $this->_unmapped))
            return $this->_unmapped[$key];

        throw new ORMException('Field ' . $key . ' does not exist in ' . get_class($this) . '!', [], '');
    }


    public function set($values, $value = NULL): ORM {

        if (is_object($values) AND $values instanceof \mii\web\Form) {

            $values = $values->changed_fields();

        } elseif (!is_array($values)) {
            $values = [$values => $value];
        }

        foreach ($values as $key => $value) {
            if (isset($this->_data[$key]) OR array_key_exists($key, $this->_data)) {

                if ($this->_serialize_fields !== null && in_array($key, $this->_serialize_fields)) {
                    $this->_serialize_cache[$key] = $value;
                } else {
                    if ($value !== $this->_data[$key]) {
                        $this->_changed[$key] = true;
                    }
                    $this->_data[$key] = $value;
                }

            } else {
                $this->_unmapped[$key] = $value;
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
    public function __isset($name) {
        return isset($this->_data[$name]);
    }

    /**
     * Gets an array version of the model
     *
     * @return array
     */
    public function to_array(array $properties = []): array {
        if (empty($properties)) {
            return $this->_data;
        }

        return Arr::to_array($this, $properties);
    }

    /**
     * Checks if the field (or any) was changed
     *
     * @param string|array $field_name
     * @return bool
     */

    public function changed($field_name = null): bool {
        // For not loaded models there is no way to detect changes.
        if (!$this->loaded())
            return true;

        if ($field_name === null) {
            return count($this->_changed) > 0;
        }

        if (is_array($field_name)) {
            return count(array_intersect($field_name, array_keys($this->_changed)));
        }

        return isset($this->_changed[$field_name]);
    }

    /**
     * Determine if this model is loaded.
     *
     * @return bool
     */
    public function loaded($value = null) {
        if ($value !== null)
            $this->__loaded = (bool)$value;

        return (bool)$this->__loaded;
    }

    /**
     * Saves the model to your database.
     *
     * @param mixed $validation a manual validation object to combine the model properties with
     *
     * @return int Affected rows
     */
    public function update() {

        if ($this->_serialize_fields !== null && !empty($this->_serialize_fields))
            $this->_invalidate_serialize_cache();

        if (!(bool)$this->_changed)
            return 0;

        if ($this->on_update() === false)
            return 0;

        $this->on_change();

        $data = array_intersect_key($this->_data, $this->_changed);

        $this->cast_types($data);

        $this->raw_query()
            ->update($this->get_table())
            ->set($data)
            ->where('id', '=', $this->_data['id'])
            ->execute();

        return \Mii::$app->db->affected_rows();
    }

    protected function on_update() {
        return true;
    }

    protected function on_change() {
        return true;
    }

    /**
     * Saves the model to your database. It will do a
     * database INSERT and assign the inserted row id to $data['id'].
     *
     * @return int Inserted row id
     */
    public function create() {
        if ($this->_serialize_fields !== null && !empty($this->_serialize_fields))
            $this->_invalidate_serialize_cache();

        if ($this->on_create() === false) {
            return 0;
        }

        $this->on_change();

        $this->cast_types($this->_data);

        $columns = array_keys($this->_data);
        $this->raw_query()
            ->insert($this->get_table())
            ->columns($columns)
            ->values($this->_data)
            ->execute();

        $this->__loaded = true;

        $this->_data['id'] = \Mii::$app->db->inserted_id();

        return $this->_data['id'];
    }

    protected function on_create() {
        return true;

    }

    /**
     * Deletes the current object's associated database row.
     * The object will still contain valid data until it is destroyed.
     *
     */
    public function delete() : void {
        if ($this->loaded()) {
            $this->__loaded = false;

            $this->raw_query()
                ->delete($this->get_table())
                ->where('id', '=', $this->_data['id'])
                ->execute();
        }

        throw new ORMException('Cannot delete a non-loaded model ' . get_class($this) . '!', [], []);
    }

    protected function _invalidate_serialize_cache(): void {
        if ($this->_serialize_fields === null || empty($this->_serialize_cache))
            return;

        foreach ($this->_serialize_fields as $key) {

            $value = isset($this->_serialize_cache[$key])
                ? $this->_serialize_value($this->_serialize_cache[$key])
                : $this->_serialize_value($this->_data[$key]);

            if ($value !== $this->_data[$key]) {
                $this->_data[$key] = $value;

                if ($this->__loaded)
                    $this->_changed[$key] = true;
            }

        }
    }

    protected function _serialize_value($value) {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    protected function _unserialize_value($key) {
        if (!array_key_exists($key, $this->_serialize_cache)) {
            assert(is_string($this->_data[$key]), 'Unserialized field must have a string value');
            $this->_serialize_cache[$key] = json_decode($this->_data[$key], TRUE);
        }
        return $this->_serialize_cache[$key];
    }

    private function cast_types(array &$data) : void {
        $schema = $this->get_tables_schema();

        foreach ($data as $key => $value) {
            if (!isset($schema[$key]))
                continue;

            switch($schema[$key]['type']){
                case 'int':
                    $data[$key] = (int)$value;
                    break;
                case 'bigint':
                    if(!$value)
                        $data[$key] = 0;
                    break;
                case 'double':
                case 'float':
                    if(!$value)
                        $data[$key] = 0.0;
                    break;
            }
        }
    }

    private function convert_type_names($type) {

        if ($type === 'int' || $type === 'smallint' || $type === 'tinyint')
            return 'int';

        if($type === 'bigint')
            return 'bigint';

        if($type === 'double' || $type === 'float')
            return 'float';

        return 'string';
    }


    private function get_tables_schema() {

        static $table_infos = [];

        $cache_id = 'db_schema_140' . $this->get_table();

        if(isset($table_infos[$cache_id]))
            return $table_infos[$cache_id];


        if (null === ($columns = get_cached($cache_id))) {
            $table_info = DB::select("SHOW FULL COLUMNS FROM " . $this->get_table())->to_array();


            $columns = [];
            foreach ($table_info as $info) {
                $column = [];

                $info = array_change_key_case($info, CASE_LOWER);


                $column_name = $info['field'];
                $column['allow_null'] = $info['null'] === 'YES';
                $column['type'] = $info['type'];

                if (preg_match('/^(\w+)(?:\(([^\)]+)\))?/', $info['type'], $matches)) {

                    $column['type'] = $this->convert_type_names(strtolower($matches[1]));

                    $type = strtolower($matches[1]);


                    if (!empty($matches[2])) {
                        if ($type === 'enum') {
                        } else {
                            $values = explode(',', $matches[2]);
                            $column['size'] = (int)$values[0];
                            if (isset($values[1])) {
                                $column['scale'] = (int)$values[1];
                            }
                            if ($column['size'] === 1 && $type === 'bit') {
                                $column['type'] = 'boolean';
                            } elseif ($type === 'bit') {
                                if ($column['size'] > 32) {
                                    $column['type'] = 'bigint';
                                } elseif ($column['size'] === 32) {
                                    $column['type'] = 'integer';
                                }
                            }
                        }
                    }
                }
                $columns[$column_name] = $column;
            }

            cache($cache_id, $columns, 3600);
            $table_infos[$cache_id] = $columns;
        }

        return $columns;
    }


}
