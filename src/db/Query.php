<?php declare(strict_types=1);

namespace mii\db;

use mii\valid\Rules;
use mii\web\Exception;
use mii\web\Pagination;

/**
 * Database Query Builder
 *
 * @copyright  (c) 2015 Lev Morozov
 * @copyright  (c) 2008-2009 Kohana Team
 */
class Query extends SelectQuery
{
    protected $_table;

    // (...)
    protected array $_columns = [];

    // VALUES (...)
    protected $_values = [];

    // SET ...
    protected $_set = [];


    /**** INSERT ****/


    /**
     * Sets the table to insert into.
     *
     * @param mixed $table table name or array($table, $alias) or object
     * @return  $this
     */
    public function table($table): self
    {
        $this->_table = $table;

        return $this;
    }

    /**
     * Set the columns that will be inserted.
     *
     * @param array $columns column names
     * @return  $this
     */
    public function columns(array $columns): self
    {
        $this->_columns = $columns;

        return $this;
    }

    /**
     * Adds or overwrites values. Multiple value sets can be added.
     *
     * @param array $values values list
     * @param   ...
     * @return  $this
     */
    public function values(...$values): self
    {
        assert(\is_array($this->_values), 'INSERT INTO ... SELECT statements cannot be combined with INSERT INTO ... VALUES');

        $this->_values = array_merge($this->_values, $values);

        return $this;
    }


    /**
     * Set the values to update with an associative array.
     *
     * @param array $pairs associative (column => value) list
     * @return  $this
     */
    public function set(array $pairs): self
    {
        foreach ($pairs as $column => $value) {
            $this->_set[] = [$column, $value];
        }

        return $this;
    }

    /**
     * Use a sub-query to for the inserted values.
     *
     * @param Query $query Database_Query of SELECT type
     * @return  $this
     */
    public function subselect(SelectQuery $query)
    {
        assert($query->_type === Database::SELECT, 'Only SELECT queries can be combined with INSERT queries');

        $this->_values = $query;

        return $this;
    }


    public function reset()
    {
        $this->db = null;
        $this->_select =
        $this->_from =
        $this->_joins =
        $this->_where =
        $this->_group_by =
        $this->_having =
        $this->_order_by = [];

        $this->_for_update = false;
        $this->_distinct = false;

        $this->_limit =
        $this->_offset =
        $this->_last_join = null;

        $this->_table = null;
        $this->_columns =
        $this->_values = [];


        return $this;
    }

    /**
     * Compile the SQL query and return it.
     *
     * @return  string
     */
    public function compile_insert(): string
    {
        // Start an insertion query
        $query = 'INSERT INTO ' . $this->db->quote_table($this->_table);

        // Add the column names
        $query .= ' (' . implode(', ', array_map([$this->db, 'quote_column'], $this->_columns)) . ') ';

        if (\is_array($this->_values)) {

            $groups = [];

            foreach ($this->_values as $group) {
                foreach ($group as $offset => $value) {
                    $group[$offset] = $this->db->quote($value);
                }

                $groups[] = '(' . implode(', ', $group) . ')';
            }

            // Add the values
            $query .= 'VALUES ' . implode(', ', $groups);
        } else {
            // Add the sub-query
            $query .= (string)$this->_values;
        }

        return $query;
    }

    /**
     * Compile the SQL query and return it.
     */
    public function compile_update(): string
    {
        // Start an update query
        $query = 'UPDATE ' . $this->db->quote_table($this->_table);

        if (!empty($this->_joins)) {
            // Add tables to join
            $query .= ' ' . $this->_compile_join();
        }

        // Add the columns to update

        $set = [];
        foreach ($this->_set as $group) {
            // Split the set
            list ($column, $value) = $group;

            // Quote the column name
            $column = $this->db->quote_column($column);

            $value = $this->db->quote($value);

            $set[$column] = $column . ' = ' . $value;
        }

        $query .= ' SET ' . implode(', ', $set);


        if (!empty($this->_where)) {
            // Add selection conditions
            $query .= ' WHERE ' . $this->_compile_conditions($this->_where);
        }

        if (!empty($this->_order_by)) {
            // Add sorting
            $query .= $this->_compile_order_by();
        }

        if ($this->_limit !== null) {
            // Add limiting
            $query .= ' LIMIT ' . $this->_limit;
        }

        return $query;
    }

    public function compile_delete()
    {

        // Start a deletion query
        $query = 'DELETE FROM ' . $this->db->quote_table($this->_table);

        if (!empty($this->_where)) {
            // Add deletion conditions
            $query .= ' WHERE ' . $this->_compile_conditions($this->_where);
        }

        if (!empty($this->_order_by)) {
            // Add sorting
            $query .= $this->_compile_order_by();
        }

        if ($this->_limit !== null) {
            // Add limiting
            $query .= ' LIMIT ' . $this->_limit;
        }

        return $query;
    }



    /**
     * Compile the SQL query and return it. Replaces any parameters with their
     * given values.
     *
     * @param mixed $db Database instance or name of instance
     * @return  string
     */
    public function compile(Database $db = null): string
    {
        $this->db = $db ?? \Mii::$app->db;

        // Compile the SQL query
        switch ($this->_type) {
            case Database::SELECT:
                $sql = $this->compile_select();
                break;
            case Database::INSERT:
                $sql = $this->compile_insert();
                break;
            case Database::UPDATE:
                $sql = $this->compile_update();
                break;
            case Database::DELETE:
                $sql = $this->compile_delete();
                break;
        }

        return $sql;
    }


    /**
     * Set the table and columns for an insert.
     *
     * @param mixed $table table name or array($table, $alias) or object
     * @param array $insert_data "column name" => "value" assoc list
     * @return  $this
     */
    public function insert($table = null, array $insert_data = null): self
    {
        $this->_type = Database::INSERT;

        if ($table) {
            // Set the initial table name
            $this->_table = $table;
        }

        if ($insert_data) {
            $group = [];
            foreach ($insert_data as $key => $value) {
                $this->_columns[] = $key;
                $group[] = $value;
            }
            $this->_values[] = $group;
        }

        return $this;
    }

    /**
     *
     * @param string $table table name
     * @return  Query
     */
    public function update($table = null)
    {
        $this->_type = Database::UPDATE;

        if ($table !== null) {
            $this->table($table);
        }

        return $this;
    }


    public function delete($table = null)
    {
        $this->_type = Database::DELETE;

        if ($table !== null) {
            $this->table($table);
        }

        return $this;
    }


    private function get_table() : string
    {
        return $this->_table ?? $this->as_object::table();
    }
}
