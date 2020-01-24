<?php

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
class Query
{
    /**
     * @var Database
     */
    protected $db;

    // Query type
    protected $_type;

    // Return results as associative arrays or objects
    protected $_as_object = false;

    // Parameters for __construct when using object results
    /**
     * @var array
     */
    protected $_object_params = [];


    protected $_table;

    // (...)
    protected $_columns = [];

    // VALUES (...)
    protected $_values = [];

    // SET ...
    protected $_set = [];


    // SELECT ...
    protected $_select = [];

    protected $_for_update = false;

    protected $_quoted_select = [];

    // DISTINCT
    protected $_distinct = false;

    // FROM ...
    protected $_from = [];

    // JOIN ...
    protected $_joins = [];

    // GROUP BY ...
    protected $_group_by = [];

    // HAVING ...
    protected $_having = [];

    // OFFSET ...
    protected $_offset;

    // The last JOIN statement created
    protected $_last_join;

    // WHERE ...
    protected $_where = [];

    // ORDER BY ...
    protected $_order_by = [];

    // LIMIT ...
    protected $_limit;

    protected $_index_by;

    protected $_last_condition_where;


    protected $_pagination;

    /**
     * Creates a new SQL query of the specified type.
     *
     * @param   integer $type query type: Database::SELECT, Database::INSERT, etc
     * @param   string $sql query string
     */
    public function __construct($type = NULL) {
        $this->_type = $type;
    }

    /**
     * Return the SQL query string.
     *
     * @return  string
     */
    public function __toString() {
        return $this->compile();
    }


    public function as_array() {
        $this->_as_object = false;
        $this->_object_params = [];

        return $this;
    }


    /**
     * Returns results as objects
     *
     * @param   string $class classname
     * @param   array $params
     * @return  $this
     */
    public function as_object($class, array $params = NULL) {
        $this->_as_object = $class;

        if ($params) {
            // Add object parameters
            $this->_object_params = $params;
        }

        return $this;
    }



    /**** SELECT ****/


    /**
     * Sets the initial columns to select
     *
     * @param   array $columns column list
     * @return  Query
     */
    public function select(array $columns = null, bool $already_quoted = false) {
        $this->_type = Database::SELECT;

        if ($columns !== null) {
            // Set the initial columns
            if ($already_quoted === false) {
                $this->_select = $columns;
                $this->_quoted_select = [];
            } else {
                $this->_quoted_select = $columns;
                $this->_select = [];
            }
        }

        return $this;
    }


    public function for_update($enable = true) {
        $this->_for_update = $enable;

        return $this;
    }

    /**
     * Enables or disables selecting only unique columns using "SELECT DISTINCT"
     *
     * @param   boolean $value enable or disable distinct columns
     * @return  $this
     */
    public function distinct(bool $value) {
        $this->_distinct = $value;

        return $this;
    }

    /**
     * Choose the columns to select from, using an array.
     *
     * @param   array $columns list of column names or aliases
     * @return  $this
     */
    public function select_array(array $columns) {
        $this->_select = \array_merge($this->_select, $columns);

        return $this;
    }

    /**
     * Choose the tables to select "FROM ..."
     *
     * @param   mixed $table table name or array($table, $alias) or object
     * @return  $this
     */
    public function from(...$tables) {
        if(empty($this->_from)) {
            $this->_from = $tables;
        } else {
            $this->_from = \array_merge($this->_from, $tables);
        }

        return $this;
    }

    /**
     * Adds addition tables to "JOIN ...".
     *
     * @param   mixed $table column name or array($column, $alias) or object
     * @param   string $type join type (LEFT, RIGHT, INNER, etc)
     * @return  $this
     */
    public function join($table, $type = NULL) {
        $this->_joins[] = [
            'table' => $table,
            'type' => $type,
            'on' => [],
            'using' => []
        ];

        $this->_last_join = \count($this->_joins) - 1;

        return $this;
    }

    /**
     * Adds "ON ..." conditions for the last created JOIN statement.
     *
     * @param   mixed $c1 column name or array($column, $alias) or object
     * @param   string $op logic operator
     * @param   mixed $c2 column name or array($column, $alias) or object
     * @return  $this
     */
    public function on($c1, $op, $c2) {
        $this->_joins[$this->_last_join]['on'][] = [$c1, $op, $c2];

        return $this;
    }

    /**
     * Adds "USING ..." conditions for the last created JOIN statement.
     *
     * @param   string $columns column name
     * @return  $this
     */
    public function using(...$columns) {
        \call_user_func_array([$this->_last_join, 'using'], $columns);

        return $this;
    }

    /**
     * Creates a "GROUP BY ..." filter.
     *
     * @param   mixed $columns column name or array($column, $alias) or object
     * @return  $this
     */
    public function group_by(...$columns) {
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
    public function having($column = null, $op = null, $value = null) {
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
    public function and_having($column, $op, $value = NULL) {
        if ($column === null) {
            $this->_having[] = ['AND' => '('];
            $this->_last_condition_where = false;
        } elseif (\is_array($column)) {
            foreach ($column as $row) {
                $this->_having[] = ['AND' => $row];
            }
        } else {
            $this->_having[] = ['AND' => [$column, $op, $value]];
        }

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
    public function or_having($column = null, $op = null, $value = null) {
        if ($column === null) {
            $this->_having[] = ['OR' => '('];
            $this->_last_condition_where = false;
        } elseif (\is_array($column)) {
            foreach ($column as $row) {
                $this->_having[] = ['OR' => $row];
            }
        } else {
            $this->_having[] = ['OR' => [$column, $op, $value]];
        }

        return $this;
    }


    /**
     * Start returning results after "OFFSET ..."
     *
     * @param   integer $number starting result number or NULL to reset
     * @return  $this
     */
    public function offset($number) {
        $this->_offset = $number;

        return $this;
    }

    public function paginate($count = 100) {

        $this->_pagination = new Pagination([
            'block' => 'pagination',
            'total_items' => $this->count(),
            'items_per_page' => $count
        ]);

        $this
            ->offset($this->_pagination->get_offset())
            ->limit($this->_pagination->get_limit());

        return $this;
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
    public function where($column = null, $op = null, $value = null) {
        return $this->and_where($column, $op, $value);
    }

    /**
     * Alias of and_filter()
     *
     * @param   mixed $column column name or array($column, $alias) or object
     * @param   string $op logic operator
     * @param   mixed $value column value
     * @return  $this
     */
    public function filter($column, $op, $value) {
        return $this->and_filter($column, $op, $value);
    }

    /**
     * Creates a new "AND WHERE" condition for the query.
     *
     * @param   mixed $column column name or array($column, $alias) or object
     * @param   string $op logic operator
     * @param   mixed $value column value
     * @return  $this
     */
    public function and_where($column, $op = null, $value = null) {

        if ($column === null) {
            $this->_where[] = ['AND' => '('];
            $this->_last_condition_where = true;
        } elseif (\is_array($column)) {
            foreach ($column as $row) {
                $this->_where[] = ['AND' => $row];
            }
        } else {
            $this->_where[] = ['AND' => [$column, $op, $value]];
        }

        return $this;
    }


    /**
     * Creates a new "AND WHERE" condition for the query. But only for not empty values.
     *
     * @param   mixed $column column name or array($column, $alias) or object
     * @param   string $op logic operator
     * @param   mixed $value column value
     * @return  $this
     */
    public function and_filter($column, $op, $value) {
        if ($value === null || $value === "" || !Rules::not_empty((\is_string($value) ? trim($value) : $value)))
            return $this;

        return $this->and_where($column, $op, $value);
    }


    /**
     * Creates a new "OR WHERE" condition for the query.
     *
     * @param   mixed $column column name or array($column, $alias) or object
     * @param   string $op logic operator
     * @param   mixed $value column value
     * @return  $this
     */
    public function or_where($column = null, $op = null, $value = null) {
        if ($column === null) {

            $this->_where[] = ['OR' => '('];
            $this->_last_condition_where = true;

        } elseif (\is_array($column)) {

            foreach ($column as $row) {
                $this->_where[] = ['OR' => $row];
            }

        } else {
            $this->_where[] = ['OR' => [$column, $op, $value]];
        }

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
    public function or_filter($column, $op, $value) {
        if ($value === null || $value === "" || !Rules::not_empty((\is_string($value) ? trim($value) : $value)))
            return $this;

        return $this->or_where($column, $op, $value);
    }

    public function end($check_for_empty = false) {

        if ($this->_last_condition_where) {
            if ($check_for_empty !== false) {
                $group = \end($this->_where);

                if ($group AND \reset($group) === '(') {
                    \array_pop($this->_where);
                    return $this;
                }
            }

            $this->_where[] = ['' => ')'];

        } else {

            if ($check_for_empty !== false) {
                $group = \end($this->_having);

                if ($group AND \reset($group) === '(') {
                    \array_pop($this->_having);
                    return $this;
                }
            }

            $this->_having[] = ['' => ')'];

        }

        return $this;
    }

    /**
     * Closes an open "WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function and_where_close() {
        $this->_where[] = ['AND' => ')'];

        return $this;
    }

    /**
     * Closes an open "WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function or_where_close() {
        $this->_where[] = ['OR' => ')'];

        return $this;
    }


    /**
     * Applies sorting with "ORDER BY ..."
     *
     * @param   mixed $column column name or array($column, $alias) or array([$column, $direction], [$column, $direction], ...)
     * @param   string $direction direction of sorting
     * @return  $this
     */
    public function order_by($column, $direction = null) {
        if (\is_array($column) AND $direction === null) {
            $this->_order_by = $column;
        } elseif($column !== null) {
            $this->_order_by[] = [$column, $direction];
        } else {
            $this->_order_by = [];
        }

        return $this;
    }


    /**
     * Return up to "LIMIT ..." results
     *
     * @param   integer $number maximum results to return or NULL to reset
     * @return  $this
     */
    public function limit($number) {
        $this->_limit = $number;

        return $this;
    }


    /**** INSERT ****/


    /**
     * Sets the table to insert into.
     *
     * @param   mixed $table table name or array($table, $alias) or object
     * @return  $this
     */
    public function table($table) {
        $this->_table = $table;

        return $this;
    }

    /**
     * Set the columns that will be inserted.
     *
     * @param   array $columns column names
     * @return  $this
     */
    public function columns(array $columns) {
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
    public function values(...$values) {
        if (!\is_array($this->_values)) {
            throw new Exception('INSERT INTO ... SELECT statements cannot be combined with INSERT INTO ... VALUES');
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
    public function set(array $pairs) {
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
    public function subselect(Query $query) {
        assert($query->_type === Database::SELECT, 'Only SELECT queries can be combined with INSERT queries');

        $this->_values = $query;

        return $this;
    }


    public function reset() {
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
        $this->_last_join = NULL;

        $this->_table = NULL;
        $this->_columns =
        $this->_values = [];


        return $this;
    }

    /**
     * Compile the SQL query and return it.
     *
     * @return  string
     */
    public function compile_insert(): string {
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
    public function compile_update(): string {
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
            $query .= ' ' . $this->_compile_order_by();
        }

        if ($this->_limit !== NULL) {
            // Add limiting
            $query .= ' LIMIT ' . $this->_limit;
        }

        return $query;
    }

    public function compile_delete() {

        // Start a deletion query
        $query = 'DELETE FROM ' . $this->db->quote_table($this->_table);

        if (!empty($this->_where)) {
            // Add deletion conditions
            $query .= ' WHERE ' . $this->_compile_conditions($this->_where);
        }

        if (!empty($this->_order_by)) {
            // Add sorting
            $query .= ' ' . $this->_compile_order_by();
        }

        if ($this->_limit !== NULL) {
            // Add limiting
            $query .= ' LIMIT ' . $this->_limit;
        }

        return $query;
    }

    /**
     * Compile the SQL query and return it.
     *
     * @return  string
     */
    public function compile_select(): string {

        // Callback to quote tables
        $quote_table = [$this->db, 'quote_table'];

        // Start a selection query
        $query = 'SELECT ';

        if ($this->_distinct === true) {
            // Select only unique results
            $query .= 'DISTINCT ';
        }


        if(empty($this->_select)) {
            $query .= implode(', ', $this->_quoted_select);
        } else {

            $columns = $this->_quoted_select;

            foreach ($this->_select as $column) {
                if (\is_array($column)) {
                    // Use the column alias
                    $column = $this->db->quote_identifier($column);
                } else {
                    // Apply proper quoting to the column
                    $column = $this->db->quote_column($column);
                }

                $columns[] = $column;
            }
            $query .= \implode(', ', \array_unique($columns));
        }

        if (!empty($this->_from)) {
            // Set tables to select from
            $query .= ' FROM ' . \implode(', ', \array_map($quote_table, $this->_from));
        }

        if (!empty($this->_joins)) {
            // Add tables to join
            $query .= ' ' . $this->_compile_join();
        }

        if (!empty($this->_where)) {
            // Add selection conditions
            $query .= ' WHERE ' . $this->_compile_conditions($this->_where);
        }

        if (!empty($this->_group_by)) {
            // Add grouping

            $group = [];

            foreach ($this->_group_by as $column) {
                if (\is_array($column)) {
                    // Use the column alias
                    $column = $this->db->quote_identifier(end($column));
                } else {
                    // Apply proper quoting to the column
                    $column = $this->db->quote_column($column);
                }

                $group[] = $column;
            }

            $query .= ' GROUP BY ' . \implode(', ', $group);
        }

        if (!empty($this->_having)) {
            // Add filtering conditions
            $query .= ' HAVING ' . $this->_compile_conditions($this->_having);
        }

        if (!empty($this->_order_by)) {
            // Add sorting
            $query .= ' ' . $this->_compile_order_by();
        }

        if ($this->_limit !== NULL) {
            // Add limiting
            $query .= ' LIMIT ' . $this->_limit;
        }

        if ($this->_offset !== NULL) {
            // Add offsets
            $query .= ' OFFSET ' . $this->_offset;
        }

        if($this->_for_update) {
            $query .= ' FOR UPDATE';
        }

        return $query;
    }


    /**
     * Compiles an array of JOIN statements into an SQL partial.
     *
     * @return  string
     */
    protected function _compile_join(): string {
        $statements = [];

        foreach ($this->_joins as $join) {

            if ($join['type']) {
                $sql = \strtoupper($this->_type) . ' JOIN';
            } else {
                $sql = 'JOIN';
            }

            // Quote the table name that is being joined
            $sql .= ' ' . $this->db->quote_table($join['table']);

            if (!empty($join['using'])) {
                // Quote and concat the columns
                $sql .= ' USING (' . \implode(', ', \array_map(array($this->db, 'quote_column'), $join['using'])) . ')';
            } else {
                $conditions = array();
                foreach ($join['on'] as $condition) {
                    // Split the condition
                    list($c1, $op, $c2) = $condition;

                    if ($op) {
                        // Make the operator uppercase and spaced
                        $op = ' ' . \strtoupper($op);
                    }

                    // Quote each of the columns used for the condition
                    $conditions[] = $this->db->quote_column($c1) . $op . ' ' . $this->db->quote_column($c2);
                }

                // Concat the conditions "... AND ..."
                $sql .= ' ON (' . \implode(' AND ', $conditions) . ')';
            }

            $statements[] = $sql;
        }

        return \implode(' ', $statements);
    }


    /**
     * Compiles an array of conditions into an SQL partial. Used for WHERE
     * and HAVING.
     *
     * @param   array $conditions condition statements
     * @return  string
     */
    protected function _compile_conditions(array $conditions): string {
        $last_condition = NULL;

        $sql = '';
        foreach ($conditions as $group) {
            // Process groups of conditions
            foreach ($group as $logic => $condition) {
                if ($condition === '(') {
                    if (!empty($sql) AND $last_condition !== '(') {
                        // Include logic operator
                        $sql .= " $logic ";
                    }

                    $sql .= '(';
                } elseif ($condition === ')') {
                    $sql .= ')';
                } else {
                    if (!empty($sql) AND $last_condition !== '(') {
                        // Add the logic operator
                        $sql .= " $logic ";
                    }

                    // Split the condition
                    list($column, $op, $value) = $condition;

                    if ($value === null) {
                        if ($op === '=') {
                            // Convert "val = NULL" to "val IS NULL"
                            $op = 'IS ';
                        } elseif ($op === '!=') {
                            // Convert "val != NULL" to "val IS NOT NULL"
                            $op = 'IS NOT ';
                        }
                    }

                    if ($op === 'BETWEEN' AND \is_array($value)) {
                        // BETWEEN always has exactly two arguments
                        list($min, $max) = $value;

                        if (\is_string($min)) {
                            $min = $this->db->quote($min);
                        }

                        if (\is_string($max)) {
                            $max = $this->db->quote($max);
                        }

                        // Quote the min and max value
                        $value = $min . ' AND ' . $max;
                    } elseif ($op === 'IN' AND \is_array($value)) {
                        $value = '(' . implode(',', array_map([$this->db, 'quote'], $value)) . ')';

                    } elseif ($op === 'NOT IN' AND \is_array($value)) {
                        $value = '(' . implode(',', array_map([$this->db, 'quote'], $value)) . ')';

                    } else {
                        $value = \is_int($value) ? $value : $this->db->quote($value);
                    }

                    if ($column) {
                        if (\is_array($column)) {
                            // Use the column name
                            $column = $this->db->quote_identifier(\reset($column));
                        } else {
                            // Apply proper quoting to the column
                            $column = $this->db->quote_column($column);
                        }
                    }

                    // Append the statement to the query
                    $sql .= \trim($column . ' ' . $op . ' ' . $value);
                }

                $last_condition = $condition;
            }
        }

        return $sql;
    }


    /**
     * Compiles an array of ORDER BY statements into an SQL partial.
     *
     * @return  string
     */
    protected function _compile_order_by() {
        $sort = [];
        foreach ($this->_order_by as $group) {
            list ($column, $direction) = $group;

            if (\is_array($column)) {
                // Use the column alias
                $column = $this->db->quote_identifier(\end($column));
            } else {
                // Apply proper quoting to the column
                $column = $this->db->quote_column($column);
            }

            if ($direction) {
                // Make the direction uppercase
                $direction = ' ' . \strtoupper($direction);
            }

            $sort[] = $column . ' ' . $direction;
        }

        return 'ORDER BY ' . \implode(', ', $sort);
    }


    /**
     * Compile the SQL query and return it. Replaces any parameters with their
     * given values.
     *
     * @param   mixed $db Database instance or name of instance
     * @return  string
     */
    public function compile(Database $db = NULL): string {

        $this->db = ($db === null) ? \Mii::$app->db : $db;

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
     * Execute the current query on the given database.
     *
     * @param   mixed $db Database instance or name of instance
     * @param   mixed   result object classname or null for array
     * @param   array    result object constructor arguments
     * @return  Result   Result for SELECT queries
     * @return  mixed    the insert id for INSERT queries
     * @return  integer  number of affected rows for all other queries
     */
    public function execute(Database $db = NULL, $as_object = null, $object_params = NULL) {

        if ($db === null) {
            $this->db = \Mii::$app->db;
        }

        if ($as_object === null) {
            $as_object = $this->_as_object;
        }

        if ($object_params === null) {
            $object_params = $this->_object_params;
        }

        assert(in_array($this->_type, [
            Database::SELECT,
            Database::INSERT,
            Database::UPDATE,
            Database::DELETE
        ]), 'Wrong query type!');

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


        // Execute the query
        $result = $this->db->query($this->_type, $sql, $as_object, $object_params);

        if (!\is_null($this->_index_by))
            $result->index_by($this->_index_by);

        if($this->_pagination)
            $result->set_pagination($this->_pagination);

        return $result;
    }


    /**
     * Set the table and columns for an insert.
     *
     * @param   mixed $table table name or array($table, $alias) or object
     * @param   array $insert_data "column name" => "value" assoc list
     * @return  $this
     */
    public function insert($table = NULL, array $insert_data = NULL) {
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
     * @param   string $table table name
     * @return  Query
     */
    public function update($table = NULL) {
        $this->_type = Database::UPDATE;

        if ($table !== NULL) {
            $this->table($table);
        }

        return $this;
    }


    public function delete($table = NULL) {
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

        $db = \Mii::$app->db;

        $old_select = $this->_select;
        $old_quoted_select = $this->_quoted_select;
        $old_order = $this->_order_by;

        if ($this->_distinct) {
            $dt_column = \count($this->_quoted_select)
                ? $this->_quoted_select[0]
                : $db->quote_column($this->_select[0]);
            $this->select([
                [DB::expr("COUNT(DISTINCT $dt_column)"), 'count']
            ]);
        } else {
            $this->select([
                DB::expr('COUNT(*) AS `count`')
            ]);
        }
        $as_object = $this->_as_object;
        $this->_as_object = null;

        $this->_order_by = [];

        $count = $this->execute()->column('count', 0);

        $this->_select = $old_select;
        $this->_quoted_select = $old_quoted_select;
        $this->_order_by = $old_order;
        $this->_as_object = $as_object;

        return (int)$count;
    }

    /**
     * @return Result|\array
     */

    public function get() {
        return $this->execute();
    }


    public function one($find_or_fail = false) {
        $this->limit(1);
        $result = $this->execute();

        if(\count($result) > 0)
            return $result->current();

        if($find_or_fail)
            throw new ModelNotFoundException();

        return null;
    }

    public function all() {
        return $this->execute()->all();
    }


}
