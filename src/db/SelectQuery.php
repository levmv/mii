<?php declare(strict_types=1);

namespace mii\db;

use mii\valid\Rules;
use mii\web\Pagination;
use function array_map;
use function array_merge;
use function count;
use function end;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function str_replace;

/**
 * Database Query Builder
 *
 * @copyright  (c) 2015 Lev Morozov
 * @copyright  (c) 2008-2009 Kohana Team
 */
class SelectQuery
{
    /**
     * @var Database
     */
    protected Database $db;

    // Query type
    protected int $_type = Database::SELECT;

    protected ?string $modelClass = null;

    // SELECT ...
    protected array $_select = [];

    protected bool $_select_any = true;

    protected bool $_for_update = false;

    // DISTINCT
    protected bool $_distinct = false;

    // FROM ...
    protected array $_from = [];

    // JOIN ...
    protected array $_joins = [];

    // GROUP BY ...
    protected array $_group_by = [];

    // HAVING ...
    protected array $_having = [];

    // OFFSET ...
    protected ?int $_offset = null;

    // The last JOIN statement created
    protected int $_last_join;

    // WHERE ...
    protected array $_where = [];

    // ORDER BY ...
    protected array $_order_by = [];

    // LIMIT ...
    protected ?int $_limit = null;

    protected ?string $_index_by = null;

    protected bool $_last_condition_where = false;

    protected Pagination|null $_pagination = null;

    /**
     * Creates a new SQL query of the specified type.
     *
     * @param string|null $classname
     */
    public function __construct(string $classname = null)
    {
        $this->asModel($classname);
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


    public function asArray(): self
    {
        $this->modelClass = null;
        return $this;
    }


    /**
     * Returns results as objects
     * @param string|null $class classname
     * @return  $this
     */
    public function asModel(?string $class): self
    {
        $this->modelClass = $class;

        if ($class !== null && empty($this->_from)) {
            $this->_from[] = $this->modelClass::table();
        }

        return $this;
    }


    /**** SELECT ****/

    /**
     * Sets the initial columns to select
     */
    public function select(...$columns): self
    {
        $this->_type = Database::SELECT;
        if (count($columns)) {
            $this->_select = $columns;
            $this->_select_any = false;
        }

        return $this;
    }

    public function forUpdate(bool $enable = true): self
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
     * @return $this
     */
    public function selectAlso(mixed ...$columns): self
    {
        $this->_select = array_merge($this->_select, $columns);
        return $this;
    }

    /**
     * Choose the tables to select "FROM ..."
     *
     * @param string|array $table table name or array($table, $alias) or object
     * @return  $this
     */
    public function from(string|array $table): self
    {
        if (empty($this->_from)) {
            $this->_from[] = $table;
            return $this;
        }

        $table_ar = (array)$table;
        foreach ($this->_from as $index => $from) {
            $from = (array)$from;
            if ($from[0] === $table_ar[0]) {
                $this->_from[$index] = $table;
            }
        }
        return $this;
    }

    /**
     * Adds addition tables to "JOIN ...".
     *
     * @param string|array $table column name or array($column, $alias) or object
     * @param string|null  $type join type (LEFT, RIGHT, INNER, etc.)
     * @return  $this
     */
    public function join(string|array $table, string $type = null): self
    {
        $this->_joins[] = [
            'table' => $table,
            'type' => $type,
            'on' => [],
            'using' => [],
        ];

        $this->_last_join = count($this->_joins) - 1;

        return $this;
    }

    /**
     * Adds "ON ..." conditions for the last created JOIN statement.
     *
     * @param string|array $c1 column name or array($column, $alias) or object
     * @param string       $op logic operator
     * @param string|array $c2 column name or array($column, $alias) or object
     * @return  $this
     */
    public function on(string|array $c1, string $op, string|array $c2): self
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
    public function using(mixed ...$columns): self
    {
        $this->_joins[$this->_last_join]['using'] = $columns;

        return $this;
    }

    /**
     * Creates a "GROUP BY ..." filter.
     *
     * @param mixed $columns column name or object
     * @return  $this
     */
    public function groupBy(mixed ...$columns): self
    {
        $this->_group_by = array_merge($this->_group_by, $columns);

        return $this;
    }

    /**
     * Alias of and_having()
     *
     * @param mixed       $column column name or array($column, $alias) or object
     * @param string|null $op logic operator
     * @param mixed       $value column value
     * @return  $this
     */
    public function having(mixed $column = null, ?string $op = null, mixed $value = null): self
    {
        return $this->andHaving($column, $op, $value);
    }

    /**
     * Creates a new "AND HAVING" condition for the query.
     *
     * @param mixed       $column column name or array($column, $alias) or object
     * @param string|null $op logic operator
     * @param mixed       $value column value
     * @return  $this
     */
    public function andHaving(mixed $column, ?string $op, mixed $value = null): self
    {
        if ($column === null) {
            $this->_having[] = ['AND' => '('];
            $this->_last_condition_where = false;
        } elseif (is_array($column)) {
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
     * @param mixed       $column column name or array($column, $alias) or object
     * @param string|null $op logic operator
     * @param mixed       $value column value
     * @return  $this
     */
    public function orHaving(mixed $column = null, ?string $op = null, mixed $value = null): self
    {
        if ($column === null) {
            $this->_having[] = ['OR' => '('];
            $this->_last_condition_where = false;
        } elseif (is_array($column)) {
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
     * @param int|null $number starting result number or null to reset
     * @return  $this
     */
    public function offset(?int $number): self
    {
        $this->_offset = $number;

        return $this;
    }

    public function paginate($count = 100, string $block_name = 'pagination'): static
    {
        $this->_pagination = new Pagination([
            'block' => $block_name,
            'total_items' => $this->count(),
            'items_per_page' => $count,
        ]);

        $this
            ->offset($this->_pagination->getOffset())
            ->limit($this->_pagination->getLimit());

        return $this;
    }


    /***** WHERE ****/

    /**
     * @param string $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed  $value column value
     * @return  $this
     */
    public function where(string $column, string $op, mixed $value): self
    {
        $this->_where[] = ['AND' => [$column, $op, $value]];
        return $this;
    }

    /**
     * Alias of andFilter()
     *
     * @param string $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed  $value column value
     * @return  $this
     */
    public function filter(string $column, string $op, mixed $value): self
    {
        return $this->andFilter($column, $op, $value);
    }

    /**
     * Creates a new "AND WHERE" condition for the query.
     *
     * @param string|null $column column name or array($column, $alias) or object
     * @param string|null $op logic operator
     * @param mixed|null  $value column value
     * @return  $this
     */
    public function andWhere(?string $column = null, string $op = null, mixed $value = null): self
    {
        if ($column === null) {
            $this->_where[] = ['AND' => '('];
            $this->_last_condition_where = true;
        } else {
            $this->_where[] = ['AND' => [$column, $op, $value]];
        }

        return $this;
    }


    public function whereAll(array $conditions): self
    {
        foreach ($conditions as [$column, $op, $value]) {
            $this->andWhere($column, $op, $value);
        }
        return $this;
    }


    public function whereGroup(callable $func = null): self
    {
        if ($func === null) {
            return $this->andWhere();
        }

        $this->andWhere();
        $func($this);
        $this->end();
        return $this;
    }


    public function orWhereGroup(callable $func = null): self
    {
        if ($func === null) {
            return $this->orWhere();
        }

        $this->orWhere();
        $func($this);
        $this->end();
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
    public function andFilter(string|array $column, string $op, mixed $value): self
    {
        if ($value === null || $value === '' || !Rules::required((\is_string($value) ? \trim($value) : $value))) {
            return $this;
        }

        return $this->andWhere($column, $op, $value);
    }


    /**
     * Creates a new "OR WHERE" condition for the query.
     *
     * @param mixed       $column column name or array($column, $alias) or object
     * @param string|null $op logic operator
     * @param mixed       $value column value
     * @return  $this
     */
    public function orWhere(mixed $column = null, ?string $op = null, mixed $value = null): self
    {
        if ($column === null) {
            $this->_where[] = ['OR' => '('];
            $this->_last_condition_where = true;
        } else {
            $this->_where[] = ['OR' => [$column, $op, $value]];
        }

        return $this;
    }

    public function end($check_for_empty = false): self
    {
        if ($this->_last_condition_where) {
            if ($check_for_empty !== false) {
                $group = end($this->_where);

                if ($group && \reset($group) === '(') {
                    \array_pop($this->_where);
                    return $this;
                }
            }

            $this->_where[] = ['' => ')'];
        } else {
            if ($check_for_empty !== false) {
                $group = end($this->_having);

                if ($group && \reset($group) === '(') {
                    \array_pop($this->_having);
                    return $this;
                }
            }

            $this->_having[] = ['' => ')'];
        }

        return $this;
    }


    /**
     * Applies sorting with "ORDER BY ..."
     *
     * @param mixed       $column column name or array($column, $alias) or array([$column, $direction], [$column, $direction], ...)
     * @param string|null $direction direction of sorting
     * @return  $this
     */
    public function orderBy(mixed $column, ?string $direction = null): self
    {
        if (is_array($column) && $direction === null) {
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
    public function limit(int $number): self
    {
        $this->_limit = $number;

        return $this;
    }


    public function when($condition, callable $callback): self
    {
        if ($condition) {
            return $callback($this, $condition);
        }
        return $this;
    }


    /**
     * Compile the SQL query and return it.
     *
     * @return  string
     */
    public function compileSelect(): string
    {
        $query = ($this->_distinct === true)
            ? 'SELECT DISTINCT '
            : 'SELECT ';

        if (empty($this->_from)) {
            \assert(!empty($this->modelClass), "You must specify 'from' table or set model class");
            /** @noinspection PhpUndefinedMethodInspection */
            $table = $this->modelClass::table();
            $table_aliased = false;
            $table_q = $this->db->quoteTable($table);
        } else {
            // Save first (by order) table for later use, flag if it aliased and quoted name (not alias)
            $table = $this->_from[0];
            $table_aliased = is_array($table);
            $table_q = $this->db->quoteTable($table_aliased ? $table[1] : $table);
        }

        if ($this->_select_any === true) {
            $query .= "$table_q.*";
        }
        if (!empty($this->_select)) {
            if ($this->_select_any) {
                $query .= ', ';
            }

            $columns = [];

            foreach ($this->_select as $column) {
                $columns[] = $this->db->quoteColumn($column, $table_q);
            }
            \assert(count($columns) === count(\array_unique($columns)), 'Columns in select query must be unique');

            $query .= implode(', ', $columns);
        }

        // One table - most common case
        if (count($this->_from) <= 1) {
            // Why make extra function call if it not neccesary?
            $query .= $table_aliased
                ? ' FROM ' . $this->db->quoteTable($table)
                : " FROM $table_q";
        } elseif (!empty($this->_from)) {
            $query .= ' FROM ' . implode(', ', array_map($this->db->quoteTable(...), $this->_from));
        }

        if (!empty($this->_joins)) {
            // Add tables to join
            $query .= ' ' . $this->_compileJoin();
        }

        if (!empty($this->_where)) {
            // Add selection conditions
            if (count($this->_where) === 1) {
                $query .= ' WHERE ' . $this->_compileShortConditions($this->_where);
            } else {
                $query .= ' WHERE ' . $this->_compileConditions($this->_where);
            }
        }

        if (!empty($this->_group_by)) {
            // Add grouping

            $group = [];

            foreach ($this->_group_by as $column) {
                $group[] = $this->db->quoteIdentifier($column);
            }

            $query .= ' GROUP BY ' . implode(', ', $group);
        }

        if (!empty($this->_having)) {
            // Add filtering conditions
            $query .= ' HAVING ' . $this->_compileConditions($this->_having);
        }

        if (!empty($this->_order_by)) {
            // Add sorting
            $query .= $this->_compileOrderBy();
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
    protected function _compileJoin(): string
    {
        $statements = [];

        foreach ($this->_joins as $join) {
            if ($join['type']) {
                $sql = \strtoupper($join['type']) . ' JOIN';
            } else {
                $sql = 'JOIN';
            }

            // Quote the table name that is being joined
            $sql .= ' ' . $this->db->quoteTable($join['table']);

            if (!empty($join['using'])) {
                // Quote and concat the columns
                $sql .= ' USING (' . implode(', ', array_map($this->db->quoteColumn(...), $join['using'])) . ')';
            } else {
                $conditions = [];
                foreach ($join['on'] as [$c1, $op, $c2]) {
                    if ($op) {
                        // Make the operator uppercase and spaced
                        $op = ' ' . \strtoupper($op);
                    }

                    // Quote each of the columns used for the condition
                    $conditions[] = $this->db->quoteColumn($c1) . $op . ' ' . $this->db->quoteColumn($c2);
                }

                // Concat the conditions "... AND ..."
                $sql .= ' ON (' . implode(' AND ', $conditions) . ')';
            }

            $statements[] = $sql;
        }

        return implode(' ', $statements);
    }


    /**
     * Compiles an array of conditions into an SQL partial. Used for WHERE
     * and HAVING.
     *
     * @param array $conditions condition statements
     * @return  string
     * @throws DatabaseException
     */
    protected function _compileConditions(array $conditions): string
    {
        $last_condition = null;

        $sql = '';
        foreach ($conditions as $group) {
            // Process groups of conditions
            foreach ($group as $logic => $condition) {
                if ($condition === '(') {
                    if (!empty($sql) && $last_condition !== '(') {
                        // Include logic operator
                        $sql .= " $logic ";
                    }

                    $sql .= '(';
                } elseif ($condition === ')') {
                    $sql .= ')';
                } else {
                    if (!empty($sql) && $last_condition !== '(') {
                        // Add the logic operator
                        $sql .= " $logic ";
                    }

                    // Split the condition
                    [$column, $op, $value] = $condition;

                    if ($value === null) {
                        if ($op === '=') {
                            // Convert "val = null" to "val IS null"
                            $op = 'IS';
                        } elseif ($op === '!=') {
                            // Convert "val != null" to "val IS NOT null"
                            $op = 'IS NOT';
                        }
                    }

                    if ($op === 'IN' && is_array($value)) {
                        $value = '(' . implode(',', array_map($this->db->quote(...), $value)) . ')';
                    } elseif ($op === 'NOT IN' && is_array($value)) {
                        $value = '(' . implode(',', array_map($this->db->quote(...), $value)) . ')';
                    } elseif ($op === 'BETWEEN' && is_array($value)) {
                        // BETWEEN always has exactly two arguments
                        [$min, $max] = $value;

                        if (!is_int($min)) {
                            $min = $this->db->quote($min);
                        }

                        if (!is_int($max)) {
                            $max = $this->db->quote($max);
                        }

                        $value = "$min AND $max";
                    } else {
                        $value = is_int($value) ? $value : $this->db->quote($value);
                    }

                    $column = $this->quoteColumn($column);

                    // Append the statement to the query
                    $sql .= "$column $op $value";
                }

                $last_condition = $condition;
            }
        }

        return $sql;
    }

    protected function _compileShortConditions(array $conditions): string
    {
        [$column, $op, $value] = \current($conditions[0]);

        $column = $this->quoteColumn($column);

        if ($value === null) {
            if ($op === '=') {
                // Convert "val = null" to "val IS null"
                $op = 'IS';
            } elseif ($op === '!=') {
                // Convert "val != null" to "val IS NOT null"
                $op = 'IS NOT';
            }
        }

        if ($op === '=') {
            $value = is_int($value) ? $value : $this->db->quote($value);
            return "$column $op $value";
        }

        if ($op === 'IN' && is_array($value)) {
            $value = '(' . implode(',', array_map($this->db->quote(...), $value)) . ')';
            return "$column $op $value";
        }

        return $this->_compileConditions($conditions);
    }


    /**
     * Compiles an array of ORDER BY statements into an SQL partial.
     *
     * @return  string
     */
    protected function _compileOrderBy(): string
    {
        $sort = [];
        foreach ($this->_order_by as [$column, $direction]) {
            $column = $this->db->quoteIdentifier($column);
            if($direction) {
                assert(in_array($direction, ['asc', 'desc']));
                $direction = $direction === 'desc' ? 'desc' : 'asc';
            }

            $sort[] = "$column $direction";
        }

        return ' ORDER BY ' . implode(', ', $sort);
    }


    protected function quoteColumn(string $column): string
    {
        $value = str_replace('`', '``', $column);

        if (\str_contains($value, '.')) {
            $parts = \explode('.', $value);

            foreach ($parts as &$part) {
                // Quote each of the parts
                $part = "`$part`";
            }

            $value = implode('.', $parts);
        } else {
            $value = "`$value`";
        }
        return $value;
    }


    /**
     * Compile the SQL query and return it. Replaces any parameters with their
     * given values.
     *
     * @param Database|null $db Database instance or name of instance
     * @return  string
     */
    public function compile(Database $db = null): string
    {
        $this->db = $db ?? \Mii::$app->db;

        return $this->compileSelect();
    }


    /**
     * Execute the current query on the given database.
     *
     * @param Database|null $db Database instance or name of instance
     * @return Result|int|string Result for SELECT queries, lastId for INSERT, affected rows for UPDATE
     */
    public function execute(Database $db = null): Result|int|string
    {
        if ($db === null) {
            $this->db = \Mii::$app->db;
        }

        $sql = $this->compile();

        // Execute the query
        $result = $this->db->query($this->_type, $sql, $this->modelClass);

        if (!\is_null($this->_index_by)) {
            $result->indexBy($this->_index_by);
        }

        if ($this->_pagination !== null) {
            $result->setPagination($this->_pagination);
        }

        return $result;
    }

    public function indexBy(?string $column): self
    {
        $this->_index_by = $column;

        return $this;
    }

    public function count(): int
    {
        $db = \Mii::$app->db;

        $old_type = $this->_type;
        $old_select = $this->_select;
        $old_any = $this->_select_any;
        $old_order = $this->_order_by;
        $old_limit = $this->_limit;
        $old_offset = $this->_offset;

        if ($this->_distinct) {
            $dt_column = $db->quoteColumn($this->_select[0]);
            $this->select(
                new Expression("COUNT(DISTINCT $dt_column)"),
            );
        } else {
            $this->select(
                new Expression('COUNT(*)'),
            );
        }

        $this->_type = Database::SELECT;
        $_model_class = $this->modelClass;
        $this->modelClass = null;
        $this->_limit = null;
        $this->_offset = null;

        $this->_order_by = [];

        $count = $this->execute()->scalar();

        $this->_type = $old_type;
        $this->_select = $old_select;
        $this->_select_any = $old_any;
        $this->_order_by = $old_order;
        $this->modelClass = $_model_class;
        $this->_limit = $old_limit;
        $this->_offset = $old_offset;

        return (int)$count;
    }

    /**
     * @return Result
     * @throws DatabaseException
     */
    public function get(): Result
    {
        return $this->execute();
    }

    /**
     * @return mixed|null
     */
    public function one(): mixed
    {
        $this->limit(1);
        $result = $this->execute();

        if (count($result) > 0) {
            return $result->current();
        }

        return null;
    }

    public function oneOrFail()
    {
        $result = $this->one();

        if ($result === null) {
            throw ((new ModelNotFoundException())->setModel((string)$this->modelClass));
        }
        return $result;
    }

    public function all(): array
    {
        return $this->execute()->all();
    }


    public function exists(): bool
    {
        return (bool)$this->select(new Expression('1'))->execute()->scalar();
    }
}
