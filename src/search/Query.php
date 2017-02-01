<?php

namespace mii\search;

use mii\db\DatabaseException;
use mii\db\Expression;

/**
 * SphinxQL Query Builder
 *
 */
class Query
{

    // Query type
    protected $_type;

    // SQL statement
    protected $_sql;


    protected $_index;

    // (...)
    protected $_columns = [];

    // VALUES (...)
    protected $_values = [];

    // SET ...
    protected $_set = [];

    // SELECT ...
    protected $_select = [];

    // FROM ...
    protected $_from = [];

    // GROUP BY ...
    protected $_group_by = [];

    // HAVING ...
    protected $_having = [];

    // OFFSET ...
    protected $_offset;

    // OPTION ...
    protected $_option = [];

    // WHERE ...
    protected $_where = [];

    // MATCH
    protected $_match = [];

    // ORDER BY ...
    protected $_order_by = [];

    // LIMIT ...
    protected $_limit;

    // Quoted query parameters
    protected $_parameters = [];


    /**
     * Creates a new SQL query of the specified type.
     *
     * @param   integer $type query type: Database::SELECT, Database::INSERT, etc
     * @param   string $sql query string
     * @return  void
     */
    public function __construct($sql = NULL, $type = NULL)
    {
        $this->_type = $type;
        $this->_sql = $sql;
    }

    /**
     * Return the SQL query string.
     *
     * @return  string
     */
    public function __toString()
    {
        try {
            // Return the SQL string
            return $this->compile();
        } catch (DatabaseException $e) {
            return DatabaseException::text($e);
        }
    }

    /**
     * Get the type of the query.
     *
     * @return  integer
     */
    public function type()
    {
        return $this->_type;
    }


    /**** SELECT ****/


    /**
     * Sets the initial columns to select
     *
     * @param   array $columns column list
     * @return  Query
     */
    public function select(array $columns = NULL)
    {
        $this->_type = Database::SELECT;

        if (!empty($columns)) {
            // Set the initial columns
            $this->_select = $columns;
        }

        return $this;
    }

    /**
     * Choose the indexes to select "FROM ..."
     *
     * @param   mixed $indexes index name or array($index, $alias) or object
     * @return  $this
     */
    public function from($indexes)
    {
        $indexes = func_get_args();

        $this->_from = array_merge($this->_from, $indexes);

        return $this;
    }


    /**
     * Creates a "GROUP BY ..." filter.
     *
     * @param   mixed $columns column name or array($column, $alias) or object
     * @return  $this
     */
    public function group_by($columns)
    {
        $columns = func_get_args();

        $this->_group_by = array_merge($this->_group_by, $columns);

        return $this;
    }

    /**
     * Alias of and_having()
     *
     * @param   mixed $column column name or array($column, $alias) or object
     * @param   string $op logic operator
     * @param   mixed $value column value
     * @return  $this
     */
    public function having($column, $op, $value = NULL)
    {
        return $this->and_having($column, $op, $value);
    }

    /**
     * Creates a new "AND HAVING" condition for the query.
     *
     * @param   mixed $column column name or array($column, $alias) or object
     * @param   string $op logic operator
     * @param   mixed $value column value
     * @return  $this
     */
    public function and_having($column, $op, $value = NULL)
    {
        $this->_having[] = ['AND' => [$column, $op, $value]];

        return $this;
    }

    /**
     * Creates a new "OR HAVING" condition for the query.
     *
     * @param   mixed $column column name or array($column, $alias) or object
     * @param   string $op logic operator
     * @param   mixed $value column value
     * @return  $this
     */
    public function or_having($column, $op, $value = NULL)
    {
        $this->_having[] = ['OR' => [$column, $op, $value]];

        return $this;
    }

    /**
     * Alias of and_having_open()
     *
     * @return  $this
     */
    public function having_open()
    {
        return $this->and_having_open();
    }

    /**
     * Opens a new "AND HAVING (...)" grouping.
     *
     * @return  $this
     */
    public function and_having_open()
    {
        $this->_having[] = ['AND' => '('];

        return $this;
    }

    /**
     * Opens a new "OR HAVING (...)" grouping.
     *
     * @return  $this
     */
    public function or_having_open()
    {
        $this->_having[] = ['OR' => '('];

        return $this;
    }

    /**
     * Closes an open "AND HAVING (...)" grouping.
     *
     * @return  $this
     */
    public function having_close()
    {
        return $this->and_having_close();
    }

    /**
     * Closes an open "AND HAVING (...)" grouping.
     *
     * @return  $this
     */
    public function and_having_close()
    {
        $this->_having[] = ['AND' => ')'];

        return $this;
    }

    /**
     * Closes an open "OR HAVING (...)" grouping.
     *
     * @return  $this
     */
    public function or_having_close()
    {
        $this->_having[] = ['OR' => ')'];

        return $this;
    }


    /**
     * Start returning results after "OFFSET ..."
     *
     * @param   integer $number starting result number or NULL to reset
     * @return  $this
     */
    public function offset($number)
    {
        $this->_offset = $number;

        return $this;
    }

    /**
     * Compile the SQL query and return it.
     *
     * @param   mixed $db Database instance or name of instance
     * @return  string
     */
    public function compile_select($db = NULL)
    {
        if (!is_object($db)) {
            // Get the database instance
            $db = \Mii::$app->sphinx;
        }

        // Callback to quote columns
        $quote_column = [$db, 'quote_column'];

        // Start a selection query
        $query = 'SELECT ';

        if (empty($this->_select)) {
            // Select all columns
            $query .= '*';
        } else {
            // Select all columns
            $columns = [];

            foreach ($this->_select as $column) {
                if (is_array($column)) {
                    // Use the column alias
                    $column = $db->quote_identifier($column);
                } else {
                    // Apply proper quoting to the column
                    $column = $db->quote_column($column);
                }

                $columns[] = $column;
            }

            // Select all columns
            $query .= implode(', ', array_unique($columns));
        }

        if (!empty($this->_from)) {
            $query .= ' FROM ' . implode(', ', array_unique(array_map([$db, 'quote_index'], $this->_from)));
        }

        if (!empty($this->_where) or !empty($this->_match)) {
            // Add selection conditions
            $query .= ' WHERE ';

            if(!empty($this->_match)) {
                $query .= 'MATCH(' .$this->_compile_match($db, $this->_match).')';
            }

            $query .= $this->_compile_conditions($db, $this->_where);
        }

        if (!empty($this->_group_by)) {
            // Add grouping
            $query .= ' ' . $this->_compile_group_by($db, $this->_group_by);
        }

        if (!empty($this->_having)) {
            // Add filtering conditions
            $query .= ' HAVING ' . $this->_compile_conditions($db, $this->_having);
        }

        if (!empty($this->_order_by)) {
            // Add sorting
            $query .= ' ' . $this->_compile_order_by($db, $this->_order_by);
        }

        if ($this->_limit !== NULL) {

            $query .= ' LIMIT ';
            if ($this->_offset !== NULL) {
                // Add offsets
                $query .= $this->_offset.', ';
            }

            // Add limiting
            $query .=  $this->_limit;
        }



        if($this->_option) {

            $query .= ' OPTION '.implode(', ', $this->_option);
        }

        $this->_sql = $query;

        return $query;
    }


    /***** WHERE ****/

    /**
     * Alias of and_where()
     *
     * @param   mixed $column column name or array($column, $alias) or object
     * @param   string $op logic operator
     * @param   mixed $value column value
     * @return  $this
     */
    public function where($column, $op, $value)
    {
        return $this->and_where($column, $op, $value);
    }

    /**
     * Creates a new "AND WHERE" condition for the query.
     *
     * @param   mixed $column column name or array($column, $alias) or object
     * @param   string $op logic operator
     * @param   mixed $value column value
     * @return  $this
     */
    public function and_where($column, $op, $value)
    {
        $this->_where[] = ['AND' => [$column, $op, $value]];

        return $this;
    }

    /**
     * Creates a new "OR WHERE" condition for the query.
     *
     * @param   mixed $column column name or array($column, $alias) or object
     * @param   string $op logic operator
     * @param   mixed $value column value
     * @return  $this
     */
    public function or_where($column, $op, $value)
    {
        $this->_where[] = ['OR' => [$column, $op, $value]];

        return $this;
    }

    /**
     * Alias of and_where_open()
     *
     * @return  $this
     */
    public function where_open()
    {
        return $this->and_where_open();
    }

    /**
     * Opens a new "AND WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function and_where_open()
    {
        $this->_where[] = ['AND' => '('];

        return $this;
    }

    /**
     * Opens a new "OR WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function or_where_open()
    {
        $this->_where[] = ['OR' => '('];

        return $this;
    }

    /**
     * Closes an open "WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function where_close()
    {
        return $this->and_where_close();
    }

    /**
     * Closes an open "WHERE (...)" grouping or removes the grouping when it is
     * empty.
     *
     * @return  $this
     */
    public function where_close_empty()
    {
        $group = end($this->_where);

        if ($group AND reset($group) === '(') {
            array_pop($this->_where);

            return $this;
        }

        return $this->where_close();
    }

    /**
     * Closes an open "WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function and_where_close()
    {
        $this->_where[] = ['AND' => ')'];

        return $this;
    }

    /**
     * Closes an open "WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function or_where_close()
    {
        $this->_where[] = ['OR' => ')'];

        return $this;
    }

    public function option($option) {
        $this->_option[] = $option;

        return $this;
    }


    /**
     * Applies sorting with "ORDER BY ..."
     *
     * @param   mixed $column column name or array($column, $alias) or array([$column, $direction], [$column, $direction], ...)
     * @param   string $direction direction of sorting
     * @return  $this
     */
    public function order_by($column, $direction = null)
    {
        if(is_array($column) AND $direction === null) {
            $this->_order_by = $column;
        } else {
            $this->_order_by[] = [$column, $direction];

        }

        return $this;
    }

    /**
     * Return up to "LIMIT ..." results
     *
     * @param   integer $number maximum results to return or NULL to reset
     * @return  $this
     */
    public function limit($number)
    {
        $this->_limit = $number;

        return $this;
    }


    public function match($column, $value = null)
    {
        $this->_match[] = [$column, $value];

        return $this;
    }

    /**** INSERT ****/



    /**
     * Sets the table to insert into.
     *
     * @param   mixed $table table name or array($table, $alias) or object
     * @return  $this
     */
    public function table($table)
    {
        $this->_table = $table;

        return $this;
    }

    /**
     * Set the columns that will be inserted.
     *
     * @param   array $columns column names
     * @return  $this
     */
    public function columns(array $columns)
    {
        $this->_columns = $columns;

        return $this;
    }

    /**
     * Adds or overwrites values. Multiple value sets can be added.
     *
     * @param   array $values values list
     * @param   ...
     * @return  $this
     */
    public function values(...$values)
    {
        if (!is_array($this->_values)) {
            throw new DatabaseException('INSERT INTO ... SELECT statements cannot be combined with INSERT INTO ... VALUES');
        }

        $this->_values = array_merge($this->_values, $values);

        return $this;
    }


    /**
     * Set the values to update with an associative array.
     *
     * @param   array $pairs associative (column => value) list
     * @return  $this
     */
    public function set(array $pairs)
    {
        foreach ($pairs as $column => $value) {
            $this->_set[] = [$column, $value];
        }

        return $this;
    }

    /**
     * Use a sub-query to for the inserted values.
     *
     * @param   object $query Database_Query of SELECT type
     * @return  $this
     */
    public function subselect(Query $query)
    {
        if ($query->type() !== Database::SELECT) {
            throw new DatabaseException('Only SELECT queries can be combined with INSERT queries');
        }

        $this->_values = $query;

        return $this;
    }

    /**
     * Compile the SQL query and return it.
     *
     * @param   mixed $db Database instance or name of instance
     * @return  string
     */
    public function compile_insert($db = NULL)
    {
        if (!is_object($db)) {
            // Get the database instance
            $db = \Mii::$app->db;
        }

        // Start an insertion query
        $query = 'INSERT INTO ' . $db->quote_table($this->_table);

        // Add the column names
        $query .= ' (' . implode(', ', array_map([$db, 'quote_column'], $this->_columns)) . ') ';

        if (is_array($this->_values)) {

            $groups = [];

            foreach ($this->_values as $group) {
                foreach ($group as $offset => $value) {
                    if ((is_string($value) AND array_key_exists($value, $this->_parameters)) === false) {
                        // Quote the value, it is not a parameter
                        $group[$offset] = $db->quote($value);
                    }
                }

                $groups[] = '(' . implode(', ', $group) . ')';
            }

            // Add the values
            $query .= 'VALUES ' . implode(', ', $groups);
        } else {
            // Add the sub-query
            $query .= (string)$this->_values;
        }

        $this->_sql = $query;

        return $query;
    }

    /**
     * Compile the SQL query and return it.
     *
     * @param   mixed $db Database instance or name of instance
     * @return  string
     */
    public function compile_update($db = NULL)
    {
        if (!is_object($db)) {
            // Get the database instance
            $db = \Mii::$app->db;
        }

        // Start an update query
        $query = 'UPDATE ' . $db->quote_table($this->_table);

        if (!empty($this->_joins)) {
            // Add tables to join
            $query .= ' ' . $this->_compile_join($db, $this->_joins);
        }


        // Add the columns to update
        $query .= ' SET ' . $this->_compile_set($db, $this->_set);


        if (!empty($this->_where)) {
            // Add selection conditions
            $query .= ' WHERE ' . $this->_compile_conditions($db, $this->_where);
        }

        if (!empty($this->_order_by)) {
            // Add sorting
            $query .= ' ' . $this->_compile_order_by($db, $this->_order_by);
        }

        if ($this->_limit !== NULL) {
            // Add limiting
            $query .= ' LIMIT ' . $this->_limit;
        }

        $this->_sql = $query;

        return $query;
    }

    public function compile_delete($db = NULL)
    {
        if ( ! is_object($db))
        {
            // Get the database instance
            $db = Database::instance($db);
        }

        // Start a deletion query
        $query = 'DELETE FROM '.$db->quote_table($this->_table);

        if ( ! empty($this->_where))
        {
            // Add deletion conditions
            $query .= ' WHERE '.$this->_compile_conditions($db, $this->_where);
        }

        if ( ! empty($this->_order_by))
        {
            // Add sorting
            $query .= ' '.$this->_compile_order_by($db, $this->_order_by);
        }

        if ($this->_limit !== NULL)
        {
            // Add limiting
            $query .= ' LIMIT '.$this->_limit;
        }

        $this->_sql = $query;

        return $query;
    }



    public function reset()
    {
        $this->_select =
        $this->_from =
        $this->_joins =
        $this->_where =
        $this->_group_by =
        $this->_having =
        $this->_order_by =
        $this->_union = [];

        $this->_distinct = false;

        $this->_limit =
        $this->_offset =
        $this->_last_join = NULL;

        $this->_parameters = [];

        $this->_sql = NULL;

        $this->_table = NULL;
        $this->_columns =
        $this->_values = [];


        return $this;
    }


    /**
     * Compiles an array of conditions into an SQL partial. Used for WHERE
     * and HAVING.
     *
     * @param   object $db Database instance
     * @param   array $conditions condition statements
     * @return  string
     */
    protected function _compile_conditions(Database $db, array $conditions)
    {
        $last_condition = NULL;

        $sql = '';
        foreach ($conditions as $group) {
            // Process groups of conditions
            foreach ($group as $logic => $condition) {
                if ($condition === '(') {
                    if (!empty($sql) AND $last_condition !== '(') {
                        // Include logic operator
                        $sql .= ' ' . $logic . ' ';
                    }

                    $sql .= '(';
                } elseif ($condition === ')') {
                    $sql .= ')';
                } else {
                    if (!empty($sql) AND $last_condition !== '(') {
                        // Add the logic operator
                        $sql .= ' ' . $logic . ' ';
                    }

                    // Split the condition
                    list($column, $op, $value) = $condition;

                    if ($value === NULL) {
                        if ($op === '=') {
                            // Convert "val = NULL" to "val IS NULL"
                            $op = 'IS';
                        } elseif ($op === '!=') {
                            // Convert "val != NULL" to "valu IS NOT NULL"
                            $op = 'IS NOT';
                        }
                    }

                    // Database operators are always uppercase
                    $op = strtoupper($op);

                    if ($op === 'BETWEEN' AND is_array($value)) {
                        // BETWEEN always has exactly two arguments
                        list($min, $max) = $value;

                        if ((is_string($min) AND array_key_exists($min, $this->_parameters)) === false) {
                            // Quote the value, it is not a parameter
                            $min = $db->quote($min);
                        }

                        if ((is_string($max) AND array_key_exists($max, $this->_parameters)) === false) {
                            // Quote the value, it is not a parameter
                            $max = $db->quote($max);
                        }

                        // Quote the min and max value
                        $value = $min . ' AND ' . $max;
                    } elseif ((is_string($value) AND array_key_exists($value, $this->_parameters)) === false) {
                        // Quote the value, it is not a parameter
                        $value = $db->quote($value);
                    }

                    if ($column) {
                        if (is_array($column)) {
                            // Use the column name
                            $column = $db->quote_identifier(reset($column));
                        } else {
                            // Apply proper quoting to the column
                            $column = $db->quote_column($column);
                        }
                    }

                    // Append the statement to the query
                    $sql .= trim($column . ' ' . $op . ' ' . $value);
                }

                $last_condition = $condition;
            }
        }

        return $sql;
    }

    protected function _compile_match(Database $db, array $values)
    {

        $set = [];
        foreach ($values as $group) {
            // Split the set
            list ($column, $value) = $group;

            // Quote the column name
            //$column = $db->quote_column($column);

            if (! ($value instanceof Expression) ) {
                // Quote the value
                $value = $db->escape_match($value);
            }

            if($value === null) {
                $set[$column] = $column;
            } else {
                $set[$column] = $column . ' ' . $value;
            }
        }

        return "'".implode(' ', $set)."'";

    }

    /**
     * Compiles an array of set values into an SQL partial. Used for UPDATE.
     *
     * @param   object $db Database instance
     * @param   array $values updated values
     * @return  string
     */
    protected function _compile_set(Database $db, array $values)
    {
        $set = [];
        foreach ($values as $group) {
            // Split the set
            list ($column, $value) = $group;

            // Quote the column name
            $column = $db->quote_column($column);

            if ((is_string($value) AND array_key_exists($value, $this->_parameters)) === false) {
                // Quote the value, it is not a parameter
                $value = $db->quote($value);
            }

            $set[$column] = $column . ' = ' . $value;
        }

        return implode(', ', $set);
    }

    /**
     * Compiles an array of GROUP BY columns into an SQL partial.
     *
     * @param   object $db Database instance
     * @param   array $columns
     * @return  string
     */
    protected function _compile_group_by(Database $db, array $columns)
    {
        $group = [];

        foreach ($columns as $column) {
            if (is_array($column)) {
                // Use the column alias
                $column = $db->quote_identifier(end($column));
            } else {
                // Apply proper quoting to the column
                $column = $db->quote_column($column);
            }

            $group[] = $column;
        }

        return 'GROUP BY ' . implode(', ', $group);
    }

    /**
     * Compiles an array of ORDER BY statements into an SQL partial.
     *
     * @param   object $db Database instance
     * @param   array $columns sorting columns
     * @return  string
     */
    protected function _compile_order_by(Database $db, array $columns)
    {
        $sort = [];
        foreach ($columns as $group) {
            list ($column, $direction) = $group;

            if (is_array($column)) {
                // Use the column alias
                $column = $db->quote_identifier(end($column));
            } else {
                // Apply proper quoting to the column
                $column = $db->quote_column($column);
            }

            if ($direction) {
                // Make the direction uppercase
                $direction = ' ' . strtoupper($direction);
            }

            $sort[] = $column . $direction;
        }

        return 'ORDER BY ' . implode(', ', $sort);
    }


    /**
     * Compile the SQL query and return it. Replaces any parameters with their
     * given values.
     *
     * @param   mixed $db Database instance or name of instance
     * @return  string
     */
    public function compile($db = NULL)
    {
        if (!is_object($db)) {
            // Get the database instance
            $db = Database::instance($db);
        }

        // Import the SQL locally
        $sql = $this->_sql;

        if (!empty($this->_parameters)) {
            // Quote all of the values
            $values = array_map([$db, 'quote'], $this->_parameters);

            // Replace the values in the SQL
            $sql = strtr($sql, $values);
        }

        return $sql;
    }


    /**
     * Execute the current query on the given database.
     *
     * @param   mixed $db Database instance or name of instance
     * @param   mixed $as_object result object classname, TRUE for stdClass or FALSE for array
     * @param   array $object_params result object constructor arguments
     * @return  Result   Result for SELECT queries
     * @return  mixed    the insert id for INSERT queries
     * @return  integer  number of affected rows for all other queries
     */
    public function execute(Database $db = NULL, $as_object = NULL, $object_params = NULL)
    {

        if (!is_object($db)) {
            // Get the database instance
            $db = \Mii::$app->sphinx;
        }

        // Compile the SQL query
        switch ($this->_type) {
            case Database::SELECT:
                $sql = $this->compile_select($db);
                break;
            case Database::INSERT:
                $sql = $this->compile_insert($db);
                break;
            case Database::UPDATE:
                $sql = $this->compile_update($db);
                break;
            case Database::DELETE:
                $sql = $this->compile_delete($db);
                break;
        }

        // Execute the query
        $result =  $db->query($this->_type, $sql);


        return $result;
    }


    /**
     * Set the table and columns for an insert.
     *
     * @param   mixed $table table name or array($table, $alias) or object
     * @param   array $insert_data "column name" => "value" assoc list
     * @return  $this
     */
    public function insert($table = NULL, array $insert_data = NULL)
    {
        $this->_type = Database::INSERT;

        if ($table) {
            // Set the initial table name
            $this->_table = $table;
        }

        if ($insert_data) {
            $group = [];
            foreach($insert_data as $key => $value) {
                $this->_columns[] = $key;
                $group[] = $value;
            }
            $this->_values[] = $group;
        }

        return $this;
    }

    /**
     *
     * @param   string $table table name
     * @return  Query
     */
    public function update($table = NULL)
    {
        $this->_type = Database::UPDATE;

        if ($table !== NULL) {
            $this->table($table);
        }

        return $this;
    }


    public function delete($table = NULL)
    {
        $this->_type = Database::DELETE;

        if ($table !== NULL) {
            $this->table($table);
        }

        return $this;
    }

    public function index_by($column) {
        $this->_index_by = $column;

        return $this;
    }

    public function count() {
        $this->_type = Database::SELECT;

        $old_select = $this->_select;
        $old_order = $this->_order_by;

        $this->_select = [DB::expr('COUNT(*)')];
        $as_object = $this->_as_object;
        $this->_as_object = null;

        $this->_order_by = [];


        $result = $this->execute();

        $count =  $this->execute()->column('COUNT(*)', 0);

        $this->_select = $old_select;
        $this->_order_by = $old_order;
        $this->_as_object = $as_object;

        return $count;
    }


    public function get()
    {
        return $this->execute();
    }


    public function one()
    {
        $this->limit(1);
        $result = $this->execute();

        return count($result) > 0 ? $result->current() : null;
    }

    public function all()
    {
        return $this->execute()->all();
    }



}
