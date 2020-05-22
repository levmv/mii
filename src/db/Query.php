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
    protected Database $db;

    // Query type
    protected $_type;

    // Return results as associative arrays or objects
    protected $_as_object = false;

    // Parameters for __construct when using object results
    /**
     * @var array
     */
    protected ?array $_object_params = null;


    protected $_table;

    // (...)
    protected array $_columns = [];

    // VALUES (...)
    protected $_values = [];

    // SET ...
    protected $_set = [];


    // SELECT ...
    protected array $_select = [];

    protected bool $_select_any = false;

    protected bool $_for_update = false;

    // DISTINCT
    protected bool $_distinct = false;

    // FROM ...
    protected array $_from = [];

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
     * @param integer $type query type: Database::SELECT, Database::INSERT, etc
     */
    public function __construct($type = null)
    {
        $this->_type = $type;
    }

    /**
     * Return the SQL query string.
     *
     * @return  string
     */
    public function __toString(): string
    {
        return $this->compile();
    }


    public function as_array(): self
    {
        $this->_as_object = false;
        // note, we doesnt clean here _object_params value

        return $this;
    }


    /**
     * Returns results as objects
     *
     * @param string $class classname
     * @param array  $params
     * @return  $this
     */
    public function as_object($class, array $params = null): self
    {
        $this->_as_object = $class;
        $this->_object_params = $params;

        return $this;
    }


    /**** SELECT ****/

    /**
     * Sets the initial columns to select
     *
     * Second argument used for optimization purpose. Most common use is call from ORM for
     * select like 'table.*'
     *
     * @param array     $columns column list
     * @param bool|null $any String like `table`.*
     * @return  Query
     */
    public function select(array $columns, bool $any = false): self
    {
        $this->_type = Database::SELECT;
        $this->_select = $columns;
        $this->_select_any = $any;

        return $this;
    }

    public function for_update(bool $enable = true): self
    {
        $this->_for_update = $enable;

        return $this;
    }

    /**
     * Enables or disables selecting only unique columns using "SELECT DISTINCT"
     *
     * @param boolean $value enable or disable distinct columns
     * @return  $this
     */
    public function distinct(bool $value): self
    {
        $this->_distinct = $value;

        return $this;
    }

    /**
     * Choose the columns to select from, using an array.
     * @param array $columns list of column names or aliases
     * @return  $this
     * @deprecated
     */
    public function select_array(array $columns)
    {
        $this->_select = \array_merge($this->_select, $columns);
        $this->_select_any = false;

        return $this;
    }

    /**
     * @param mixed ...$columns
     * @return $this
     */
    public function select_also(...$columns): self
    {
        $this->_select = \array_merge($this->_select, $columns);
        $this->_select_any = false;
        return $this;
    }

    /**
     * Choose the tables to select "FROM ..."
     *
     * @param mixed $table table name or array($table, $alias) or object
     * @return  $this
     */
    public function from($table): self
    {
        $this->_from[] = $table;

        return $this;
    }

    /**
     * Adds addition tables to "JOIN ...".
     *
     * @param mixed  $table column name or array($column, $alias) or object
     * @param string $type join type (LEFT, RIGHT, INNER, etc)
     * @return  $this
     */
    public function join($table, $type = null): self
    {
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
     * @param mixed  $c1 column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed  $c2 column name or array($column, $alias) or object
     * @return  $this
     */
    public function on($c1, $op, $c2): self
    {
        $this->_joins[$this->_last_join]['on'][] = [$c1, $op, $c2];

        return $this;
    }

    /**
     * Adds "USING ..." conditions for the last created JOIN statement.
     *
     * @param mixed ...$columns column name
     * @return  $this
     */
    public function using(...$columns): self
    {
        \call_user_func_array([$this->_last_join, 'using'], $columns);

        return $this;
    }

    /**
     * Creates a "GROUP BY ..." filter.
     *
     * @param mixed $columns column name or object
     * @return  $this
     */
    public function group_by(...$columns): self
    {
        $this->_group_by = array_merge($this->_group_by, $columns);

        return $this;
    }

    /**
     * Alias of and_having()
     *
     * @param mixed  $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed  $value column value
     * @return  $this
     */
    public function having($column = null, $op = null, $value = null): self
    {
        return $this->and_having($column, $op, $value);
    }

    /**
     * Creates a new "AND HAVING" condition for the query.
     *
     * @param mixed  $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed  $value column value
     * @return  $this
     */
    public function and_having($column, $op, $value = null): self
    {
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
     * @param mixed  $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed  $value column value
     * @return  $this
     */
    public function or_having($column = null, $op = null, $value = null): self
    {
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
     * @param integer $number starting result number or null to reset
     * @return  $this
     */
    public function offset(?int $number): self
    {
        $this->_offset = $number;

        return $this;
    }

    public function paginate($count = 100)
    {
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
     * @param mixed  $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed  $value column value
     * @return  $this
     */
    public function where($column = null, $op = null, $value = null): self
    {
        return $this->and_where($column, $op, $value);
    }

    /**
     * Alias of and_filter()
     *
     * @param mixed  $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed  $value column value
     * @return  $this
     */
    public function filter($column, $op, $value): self
    {
        return $this->and_filter($column, $op, $value);
    }

    /**
     * Creates a new "AND WHERE" condition for the query.
     *
     * @param mixed  $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed  $value column value
     * @return  $this
     */
    public function and_where($column, $op = null, $value = null): self
    {

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
     * @param mixed  $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed  $value column value
     * @return  $this
     */
    public function and_filter($column, $op, $value): self
    {
        if ($value === null || $value === "" || !Rules::not_empty((\is_string($value) ? trim($value) : $value)))
            return $this;

        return $this->and_where($column, $op, $value);
    }


    /**
     * Creates a new "OR WHERE" condition for the query.
     *
     * @param mixed  $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed  $value column value
     * @return  $this
     */
    public function or_where($column = null, $op = null, $value = null): self
    {
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

    public function end($check_for_empty = false): self
    {
        if ($this->_last_condition_where) {
            if ($check_for_empty !== false) {
                $group = \end($this->_where);

                if ($group and \reset($group) === '(') {
                    \array_pop($this->_where);
                    return $this;
                }
            }

            $this->_where[] = ['' => ')'];

        } else {

            if ($check_for_empty !== false) {
                $group = \end($this->_having);

                if ($group and \reset($group) === '(') {
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
    public function and_where_close(): self
    {
        $this->_where[] = ['AND' => ')'];

        return $this;
    }

    /**
     * Closes an open "WHERE (...)" grouping.
     *
     * @return  $this
     */
    public function or_where_close(): self
    {
        $this->_where[] = ['OR' => ')'];

        return $this;
    }


    /**
     * Applies sorting with "ORDER BY ..."
     *
     * @param mixed  $column column name or array($column, $alias) or array([$column, $direction], [$column, $direction], ...)
     * @param string $direction direction of sorting
     * @return  $this
     */
    public function order_by($column, $direction = null): self
    {
        if (\is_array($column) && $direction === null) {
            $this->_order_by = $column;
        } elseif ($column !== null) {
            $this->_order_by[] = [$column, $direction];
        } else {
            $this->_order_by = [];
        }

        return $this;
    }


    /**
     * Return up to "LIMIT ..." results
     *
     * @param integer $number maximum results to return or null to reset
     * @return  $this
     */
    public function limit($number): self
    {
        $this->_limit = $number;

        return $this;
    }


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
    public function subselect(Query $query)
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
            $query .= ' ' . $this->_compile_order_by();
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
            $query .= ' ' . $this->_compile_order_by();
        }

        if ($this->_limit !== null) {
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
    public function compile_select(): string
    {
        $query = ($this->_distinct === true)
            ? 'SELECT DISTINCT '
            : 'SELECT ';

        // Save first (by order) table for later use, flag if it aliased and quoted name (not alias)
        $table = $this->_from[0];
        $table_aliased = \is_array($table);
        $table_q = $this->db->quote_table($table_aliased ? \current($table) : $table);

        if ($this->_select_any === true) {
            $query .= "$table_q.*";
        } else {
            $columns = [];

            foreach ($this->_select as $column) {
                $columns[] = $this->db->quote_column($column, $table_q);
            }
            assert(count($columns) === count(array_unique($columns)), 'Columns in select query must be unique');
            $query .= \implode(', ', $columns);
        }

        // One table - most common case
        if (\count($this->_from) === 1) {
            // Why make extra function call if it not neccesary?
            $query .= $table_aliased
                ? ' FROM ' . $this->db->quote_table($table)
                : " FROM $table_q";

        } else if (!empty($this->_from)) {
            assert(empty($this->_from), 'From must not be empty');
            $query .= ' FROM ' . \implode(', ', \array_map([$this->db, 'quote_table'], $this->_from));
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
                $group[] = $this->db->quote_identifier($column);
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

        if ($this->_limit !== null) {
            // Add limiting
            $query .= ' LIMIT ' . $this->_limit;
        }

        if ($this->_offset !== null) {
            // Add offsets
            $query .= ' OFFSET ' . $this->_offset;
        }

        if ($this->_for_update) {
            $query .= ' FOR UPDATE';
        }

        return $query;
    }


    /**
     * Compiles an array of JOIN statements into an SQL partial.
     *
     * @return  string
     */
    protected function _compile_join(): string
    {
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
                foreach ($join['on'] as [$c1, $op, $c2]) {

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
     * @param array $conditions condition statements
     * @return  string
     */
    protected function _compile_conditions(array $conditions): string
    {
        $last_condition = null;

        $sql = '';
        foreach ($conditions as $group) {
            // Process groups of conditions
            foreach ($group as $logic => $condition) {
                if ($condition === '(') {
                    if (!empty($sql) and $last_condition !== '(') {
                        // Include logic operator
                        $sql .= " $logic ";
                    }

                    $sql .= '(';
                } elseif ($condition === ')') {
                    $sql .= ')';
                } else {
                    if (!empty($sql) and $last_condition !== '(') {
                        // Add the logic operator
                        $sql .= " $logic ";
                    }

                    // Split the condition
                    list($column, $op, $value) = $condition;

                    if ($value === null) {
                        if ($op === '=') {
                            // Convert "val = null" to "val IS null"
                            $op = 'IS';
                        } elseif ($op === '!=') {
                            // Convert "val != null" to "val IS NOT null"
                            $op = 'IS NOT';
                        }
                    }

                    if ($op === 'BETWEEN' and \is_array($value)) {
                        // BETWEEN always has exactly two arguments
                        [$min, $max] = $value;

                        if (!\is_int($min)) {
                            $min = $this->db->quote($min);
                        }

                        if (!\is_int($max)) {
                            $max = $this->db->quote($max);
                        }

                        $value = "$min AND $max";
                    } elseif ($op === 'IN' and \is_array($value)) {
                        $value = '(' . implode(',', array_map([$this->db, 'quote'], $value)) . ')';

                    } elseif ($op === 'NOT IN' and \is_array($value)) {
                        $value = '(' . implode(',', array_map([$this->db, 'quote'], $value)) . ')';

                    } else {
                        $value = \is_int($value) ? $value : $this->db->quote($value);
                    }

                    if ($column) {
                        $column = $this->db->quote_column($column);
                    }

                    // Append the statement to the query
                    $sql .= "$column $op $value";
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
    protected function _compile_order_by(): string
    {
        $sort = [];
        foreach ($this->_order_by as [$column, $direction]) {

            $column = $this->db->quote_identifier($column);

            $sort[] = "$column $direction";
        }

        return 'ORDER BY ' . \implode(', ', $sort);
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
     * Execute the current query on the given database.
     *
     * @param mixed $db Database instance or name of instance
     * @param mixed   result object classname or null for array
     * @param array    result object constructor arguments
     * @return  Result   Result for SELECT queries
     * @return  mixed    the insert id for INSERT queries
     * @return  integer  number of affected rows for all other queries
     * @throws DatabaseException
     */
    public function execute(Database $db = null, $as_object = null, $object_params = null)
    {

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
        ], true), 'Wrong query type!');

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

        if ($this->_pagination)
            $result->set_pagination($this->_pagination);

        return $result;
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

    public function index_by($column)
    {
        $this->_index_by = $column;

        return $this;
    }

    public function count()
    {
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

    public function get()
    {
        return $this->execute();
    }


    public function one($find_or_fail = false)
    {
        $this->limit(1);
        $result = $this->execute();

        if (\count($result) > 0)
            return $result->current();

        if ($find_or_fail)
            throw new ModelNotFoundException();

        return null;
    }

    public function one_or_fail()
    {
        $result = $this->one();

        if ($result === null)
            throw new ModelNotFoundException();

        return $result;
    }

    public function all()
    {
        return $this->execute()->all();
    }


}
