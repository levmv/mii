<?php declare(strict_types=1);

namespace mii\db;

use mii\core\Component;

/**
 * Database connection/query wrapper/helper.
 *
 *
 * @copyright  (c) 2015 Lev Morozov
 * @copyright  (c) 2008-2012 Kohana Team
 */
class Database extends Component
{
    // Query types
    public const SELECT = 1;
    public const INSERT = 2;
    public const UPDATE = 3;
    public const DELETE = 4;

    protected string $hostname = '127.0.0.1';
    protected string $username = '';
    protected ?string $password = '';
    protected string $database = '';
    protected int $port = 3306;

    protected ?string $charset = 'utf8';

    protected ?\mysqli $conn = null;

    /**
     * Connect to the database. This is called automatically when the first query is executed.
     *
     * @return  void
     * @throws  DatabaseException
     */
    public function connect(): void
    {
        try {
            $this->conn = \mysqli_connect(
                $this->hostname,
                $this->username,
                $this->password,
                $this->database,
                $this->port
            );
        } catch (\Exception $e) {
            // No connection exists
            $this->conn = null;
            $this->password = null;
            throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
        }

        $this->password = null;

        $this->conn->options(\MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

        \mysqli_report(\MYSQLI_REPORT_ERROR | \MYSQLI_REPORT_STRICT);

        if (!\is_null($this->charset)) {
            // Set the character set
            $this->conn->set_charset($this->charset);
        }
    }


    public function autoNativeTypes(bool $enable): bool
    {
        return $this->conn->options(\MYSQLI_OPT_INT_AND_FLOAT_NATIVE, $enable);
    }


    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Disconnect from the database. This is called automatically.
     *
     * @return  boolean
     */
    public function disconnect(): bool
    {
        try {
            // Database is assumed disconnected
            $status = true;

            if (\is_resource($this->conn) && $status = $this->conn->close()) {
                // Clear the connection
                $this->conn = null;
            }
        } catch (\Throwable) {
            // Database is probably not disconnected
            $status = !\is_resource($this->conn);
        }

        return $status;
    }


    public function __toString()
    {
        return 'db';
    }

    /**
     * Perform an SQL query of the given type.
     *
     * @param int|null $type Database::SELECT, Database::INSERT, etc
     * @param string $sql SQL query
     * @param mixed $asObject result object class string, TRUE for stdClass, FALSE for assoc array
     * @param array|null $params object construct parameters for result class
     * @return Result|null  Result for SELECT queries or null
     * @throws DatabaseException
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function query(?int $type, string $sql, bool|string $asObject = false, array $params = null): ?Result
    {
        // Make sure the database is connected
        !\is_null($this->conn) || $this->connect();

        \assert((config('debug') && ($benchmark = \mii\util\Profiler::start('Database', $sql))) || 1);

        // Execute the query
        try {
            $result = $this->conn->query($sql);
        } catch (\Throwable $t) {
            throw new DatabaseException("{$this->conn->error} [ $sql ]", $this->conn->errno);
        }

        if ($result === false || $this->conn->errno) {
            \assert((isset($benchmark) && \mii\util\Profiler::delete($benchmark)) || 1);

            throw new DatabaseException("{$this->conn->error} [ $sql ]", $this->conn->errno);
        }

        \assert((isset($benchmark) && \mii\util\Profiler::stop($benchmark)) || 1);

        if ($type === self::SELECT) {
            // Return an iterator of results
            return new Result($result, $asObject, $params);
        }

        return null;
    }


    public function multiQuery(string $sql): ?Result
    {
        $this->conn or $this->connect();
        \assert((config('debug') && ($benchmark = \mii\util\Profiler::start('Database', $sql))) || 1);

        // Execute the query
        $result = $this->conn->multi_query($sql);
        $affected_rows = 0;
        do {
            $affected_rows += $this->conn->affected_rows;
        } while ($this->conn->more_results() && $this->conn->next_result());

        \assert((isset($benchmark) && \mii\util\Profiler::stop($benchmark)) || 1);

        if ($result === false || $this->conn->errno) {
            throw new DatabaseException("{$this->conn->error} [ $sql ]", $this->conn->errno);
        }

        return null;
    }


    public function insertedId(): int|string
    {
        return $this->conn->insert_id;
    }


    public function affectedRows(): int
    {
        return $this->conn->affected_rows;
    }


    /**
     * Quote a value for an SQL query.
     *
     *     $db->quote(NULL);   // 'NULL'
     *     $db->quote(10);     // 10
     *     $db->quote('fred'); // 'fred'
     *
     * Objects passed to this function will be converted to strings.
     * [Expression] objects will be compiled.
     * [SelectQuery] objects will be compiled and converted to a sub-query.
     * All other objects will be converted using the `__toString` method.
     *
     * @param mixed $value any value to quote
     * @return  string
     * @throws DatabaseException
     */
    public function quote(mixed $value): string
    {
        if (\is_null($value)) {
            return 'NULL';
        }

        if (\is_int($value)) {
            return (string) $value;
        }

        if ($value === true) {
            return "'1'";
        }

        if ($value === false) {
            return "'0'";
        }

        if (\is_float($value)) {
            // Convert to non-locale aware float to prevent possible commas
            return \sprintf('%F', $value);
        }

        if (\is_array($value)) {
            return '(' . \implode(', ', \array_map([$this, __FUNCTION__], $value)) . ')';
        }

        if (\is_object($value)) {
            if ($value instanceof SelectQuery) {
                // Create a sub-query
                return '(' . $value->compile($this) . ')';
            }

            if ($value instanceof Expression) {
                // Compile the expression
                return $value->compile($this);
            }

            // Convert the object to a string
            return $this->quote((string) $value);
        }

        return $this->escape($value);
    }

    /**
     * Sanitize a string by escaping characters that could cause an SQL injection attack.
     *
     * @param string $value value to quote
     * @return  string
     * @throws DatabaseException
     */
    public function escape(string $value): string
    {
        // Make sure the database is connected
        !\is_null($this->conn) or $this->connect();

        $value = $this->conn->real_escape_string($value);

        // SQL standard is to use single-quotes for all values
        return "'$value'";
    }

    /**
     * Start a SQL transaction
     *
     * @param string|null $mode transaction mode
     * @return  boolean
     * @throws DatabaseException
     */
    public function begin(string $mode = null): bool
    {
        // Make sure the database is connected
        $this->conn or $this->connect();

        if ($mode && !$this->conn->query("SET TRANSACTION ISOLATION LEVEL $mode")) {
            throw new DatabaseException($this->conn->error, $this->conn->errno);
        }

        return (bool) $this->conn->query('START TRANSACTION');
    }

    /**
     * Commit the current transaction
     *
     * @return  boolean
     * @throws DatabaseException
     */
    public function commit(): bool
    {
        // Make sure the database is connected
        $this->conn or $this->connect();

        return (bool) $this->conn->query('COMMIT');
    }

    /**
     * Abort the current transaction
     *
     * @return  boolean
     * @throws DatabaseException
     */
    public function rollback(): bool
    {
        // Make sure the database is connected
        $this->conn or $this->connect();

        return (bool) $this->conn->query('ROLLBACK');
    }


    public function getLock($name, $timeout = 0): bool
    {
        return (bool) $this->query(
            static::SELECT,
            \strtr('SELECT GET_LOCK(:name, :timeout)', [
                ':name' => $this->quote($name),
                ':timeout' => (int) $timeout,
            ])
        )->scalar();
    }


    public function releaseLock($name): bool
    {
        return (bool) $this->query(
            static::SELECT,
            \strtr('SELECT RELEASE_LOCK(:name)', [
                ':name' => $this->quote($name),
            ])
        )->scalar();
    }


    /**
     * Quote a database column name and add the table prefix if needed.
     *
     * @param mixed $column column name or array(column, alias)
     * @param null  $table
     * @return  string
     * @uses    Database::quoteIdentifier
     */
    public function quoteColumn(mixed $column, $table = null): string
    {
        if (\is_array($column)) {
            [$column, $alias] = $column;
            $alias = \str_replace('`', '``', $alias);
        }

        if (\is_object($column) && $column instanceof SelectQuery) {
            // Create a sub-query
            $column = '(' . $column->compile($this) . ')';
        } elseif (\is_object($column) && $column instanceof Expression) {
            // Compile the expression
            $column = $column->compile($this);
        } else {
            // Convert to a string
            $column = (string) $column;

            $column = \str_replace('`', '``', $column);

            if ($column === '*') {
                return $table ? "$table.$column" : $column;
            }

            if (\str_contains($column, '.')) {
                $parts = \explode('.', $column);

                foreach ($parts as &$part) {
                    if ($part !== '*') {
                        // Quote each of the parts
                        $part = "`$part`";
                    }
                }

                $column = \implode('.', $parts);
            } else {
                $column = $table === null
                    ? "`$column`"
                    : "$table.`$column`";
            }
        }

        if (isset($alias)) {
            $column .= " AS `$alias`";
        }

        return $column;
    }


    /**
     * Quote a database table name and adds the table prefix if needed.
     *
     * @param mixed $table table name or array(table, alias)
     * @return  string
     * @uses    Database::quoteIdentifier
     */
    public function quoteTable(mixed $table): string
    {
        if (\is_array($table)) {
            [$table, $alias] = $table;
            $alias = \str_replace('`', '``', $alias);
        }

        if ($table instanceof SelectQuery) {
            // Create a sub-query
            $table = '(' . $table->compile($this) . ')';
        } elseif ($table instanceof Expression) {
            // Compile the expression
            $table = $table->compile($this);
        } else {
            // Convert to a string
            $table = (string) $table;

            $table = \str_replace('`', '``', $table);

            if (\str_contains($table, '.')) {
                $parts = \explode('.', $table);

                foreach ($parts as &$part) {
                    // Quote each of the parts
                    $part = "`$part`";
                }

                $table = \implode('.', $parts);
            } else {
                // Add the table prefix
                $table = "`$table`";
            }
        }

        if (isset($alias)) {
            $table .= " AS `$alias`";
        }

        return $table;
    }

    /**
     * Quote a database identifier
     *
     * @param mixed $value any identifier
     * @return  string
     */
    public function quoteIdentifier(mixed $value): string
    {
        if (\is_object($value) && $value instanceof SelectQuery) {
            // Create a sub-query
            $value = '(' . $value->compile($this) . ')';
        } elseif ($value instanceof Expression) {
            // Compile the expression
            $value = $value->compile($this);
        } else {
            // Convert to a string
            $value = (string) $value;

            $value = \str_replace('`', '``', $value);

            if (\str_contains($value, '.')) {
                $parts = \explode('.', $value);

                foreach ($parts as &$part) {
                    // Quote each of the parts
                    $part = "`$part`";
                }

                $value = \implode('.', $parts);
            } else {
                $value = "`$value`";
            }
        }

        return $value;
    }
}
