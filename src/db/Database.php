<?php

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

    /**
     * @var  string  the last query executed
     */
    public $last_query;

    /**
     * @var \mysqli Raw server connection
     */
    protected $_connection;


    /**
     * Disconnect from the database when the object is destroyed.
     *
     *     // Destroy the database instance
     *     unset(Database::instances[(string) $db], $db);
     *
     * [!!] Calling `unset($db)` is not enough to destroy the database, as it
     * will still be stored in `Database::$instances`.
     *
     * @return  void
     */
    public function __destruct() {
        $this->disconnect();
    }

    /**
     * Disconnect from the database. This is called automatically by [Database::__destruct].
     * Clears the database instance from [Database::$instances].
     *
     *     $db->disconnect();
     *
     * @return  boolean
     */
    public function disconnect() {
        try {
            // Database is assumed disconnected
            $status = true;

            if (\is_resource($this->_connection)) {
                if ($status = $this->_connection->close()) {
                    // Clear the connection
                    $this->_connection = NULL;
                }
            }
        } catch (\Exception $e) {
            // Database is probably not disconnected
            $status = !\is_resource($this->_connection);
        }

        return $status;
    }


    public function __toString() {
        return 'db';
    }

    /**
     * Perform an SQL query of the given type.
     *
     *     // Make a SELECT query and use objects for results
     *     $db->query(Database::SELECT, 'SELECT * FROM groups', TRUE);
     *
     *     // Make a SELECT query and use "Model_User" for the results
     *     $db->query(Database::SELECT, 'SELECT * FROM users LIMIT 1', 'Model_User');
     *
     * @param integer $type Database::SELECT, Database::INSERT, etc
     * @param string $sql SQL query
     * @param mixed $as_object result object class string, TRUE for stdClass, FALSE for assoc array
     * @param array $params object construct parameters for result class
     * @return  Result|null   Result for SELECT queries or null
     */
    public function query(?int $type, string $sql, $as_object = false, array $params = NULL): ?Result {
        // Make sure the database is connected
        ! \is_null($this->_connection) or $this->connect();

        assert(
            config('debug') &&
            ($benchmark = \mii\util\Profiler::start("Database", $sql)) ||
            true
        );

        // Execute the query
        $result = $this->_connection->query($sql);

        if ($result === false || $this->_connection->errno) {
            assert(isset($benchmark) && \mii\util\Profiler::delete($benchmark) || true);

            throw new DatabaseException("{$this->_connection->error} [ $sql ]", $this->_connection->errno);
        }

        assert(isset($benchmark) && \mii\util\Profiler::stop($benchmark) || true);

        // Set the last query
        $this->last_query = $sql;

        if ($type === Database::SELECT) {
            // Return an iterator of results
            return new Result($result, $as_object, $params);
        }

        return null;
    }


    public function inserted_id() {
        return $this->_connection->insert_id;
    }


    public function affected_rows(): int {
        return $this->_connection->affected_rows;
    }

    /**
     * Connect to the database. This is called automatically when the first
     * query is executed.
     *
     *     $db->connect();
     *
     * @return  void
     * @throws  DatabaseException
     */
    public function connect() {

        try {
            $this->_connection = mysqli_connect(
                $this->hostname,
                $this->username,
                $this->password,
                $this->database,
                $this->port
            );

        } catch (\Exception $e) {
            // No connection exists
            $this->_connection = NULL;
            $this->password = null;
            throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
        }

        $this->password = null;

        $this->_connection->options(\MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

        if (! \is_null($this->charset)) {
            // Set the character set
            $this->_connection->set_charset($this->charset);
        }

     /*   if (!empty($this->variables)) {
            // Set session variables
            $variables = [];

            foreach ($this->_config['connection']['variables'] as $var => $val) {
                $variables[] = 'SESSION ' . $var . ' = ' . $this->quote($val);
            }

            $this->_connection->query('SET ' . implode(', ', $variables));
        }*/

    }


    public function auto_native_types(bool $enable) : void
    {
        $this->_connection->options(\MYSQLI_OPT_INT_AND_FLOAT_NATIVE, $enable);
    }


    /**
     * Quote a value for an SQL query.
     *
     *     $db->quote(NULL);   // 'NULL'
     *     $db->quote(10);     // 10
     *     $db->quote('fred'); // 'fred'
     *
     * Objects passed to this function will be converted to strings.
     * [Database_Expression] objects will be compiled.
     * [Database_Query] objects will be compiled and converted to a sub-query.
     * All other objects will be converted using the `__toString` method.
     *
     * @param mixed $value any value to quote
     * @return  string
     * @uses    Database::escape
     */
    public function quote($value): string {
        if (\is_null($value)) {
            return 'NULL';
        } elseif (\is_int($value)) {
            return (int)$value;
        } elseif ($value === true) {
            return "'1'";
        } elseif ($value === false) {
            return "'0'";
        } elseif (\is_float($value)) {
            // Convert to non-locale aware float to prevent possible commas
            return sprintf('%F', $value);
        } elseif (\is_array($value)) {
            return '(' . implode(', ', array_map([$this, __FUNCTION__], $value)) . ')';
        } elseif (\is_object($value)) {
            if ($value instanceof Query) {
                // Create a sub-query
                return '(' . $value->compile($this) . ')';
            } elseif ($value instanceof Expression) {
                // Compile the expression
                return $value->compile($this);
            } else {
                // Convert the object to a string
                return $this->quote((string)$value);
            }
        }

        return $this->escape($value);
    }

    /**
     * Sanitize a string by escaping characters that could cause an SQL
     * injection attack.
     *
     *     $value = $db->escape('any string');
     *
     * @param string $value value to quote
     * @return  string
     * @throws DatabaseException
     */
    public function escape($value): string {
        // Make sure the database is connected
        ! \is_null($this->_connection) or $this->connect();

        if (($value = $this->_connection->real_escape_string((string)$value)) === false) {
            throw new DatabaseException($this->_connection->error, $this->_connection->errno);
        }

        // SQL standard is to use single-quotes for all values
        return "'$value'";
    }

    /**
     * Start a SQL transaction
     *
     *     // Start the transactions
     *     $db->begin();
     *
     *     try {
     *          DB::insert('users')->values($user1)...
     *          DB::insert('users')->values($user2)...
     *          // Insert successful commit the changes
     *          $db->commit();
     *     }
     *     catch (Database_Exception $e)
     *     {
     *          // Insert failed. Rolling back changes...
     *          $db->rollback();
     *      }
     *
     * @param string $mode transaction mode
     * @return  boolean
     */
    public function begin($mode = NULL) {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        if ($mode AND !$this->_connection->query("SET TRANSACTION ISOLATION LEVEL $mode")) {
            throw new DatabaseException($this->_connection->error, $this->_connection->errno);
        }

        return (bool)$this->_connection->query('START TRANSACTION');
    }

    /**
     * Commit the current transaction
     *
     *     // Commit the database changes
     *     $db->commit();
     *
     * @return  boolean
     */
    public function commit() {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        return (bool)$this->_connection->query('COMMIT');
    }

    /**
     * Abort the current transaction
     *
     *     // Undo the changes
     *     $db->rollback();
     *
     * @return  boolean
     */
    public function rollback() {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        return (bool)$this->_connection->query('ROLLBACK');
    }


    public function get_lock($name, $timeout = 0) : bool {

        return (bool)$this->query(
            static::SELECT,
            strtr('SELECT GET_LOCK(:name, :timeout)', [
                ':name' => $this->quote($name),
                ':timeout' => (int)$timeout
            ])
        )->scalar();
    }


    public function release_lock($name) : bool {
        return (bool)$this->query(
            static::SELECT,
            strtr('SELECT RELEASE_LOCK(:name)', [
                ':name' => $this->quote($name)
            ])
        )->scalar();
    }


    /**
     * Quote a database column name and add the table prefix if needed.
     *
     *     $column = $db->quote_column($column);
     *
     * You can also use SQL methods within identifiers.
     *
     *     $column = $db->quote_column(DB::expr('COUNT(`column`)'));
     *
     * Objects passed to this function will be converted to strings.
     * [Database_Expression] objects will be compiled.
     * [Database_Query] objects will be compiled and converted to a sub-query.
     * All other objects will be converted using the `__toString` method.
     *
     * @param mixed $column column name or array(column, alias)
     * @return  string
     * @uses    Database::quote_identifier
     */
    public function quote_column($column): string {

        if (\is_array($column)) {
            list($column, $alias) = $column;
            $alias = \str_replace('`', '``', $alias);
        }

        if (\is_object($column) AND $column instanceof Query) {
            // Create a sub-query
            $column = '(' . $column->compile($this) . ')';
        } elseif (\is_object($column) AND $column instanceof Expression) {
            // Compile the expression
            $column = $column->compile($this);
        } else {
            // Convert to a string
            $column = (string)$column;

            $column = \str_replace('`', '``', $column);

            if ($column === '*') {
                return $column;
            } elseif (\strpos($column, '.') !== false) {
                $parts = \explode('.', $column);

                foreach ($parts as & $part) {
                    if ($part !== '*') {
                        // Quote each of the parts
                        $part = "`$part`";
                    }
                }

                $column = \implode('.', $parts);
            } else {
                $column = '`' . $column . '`';
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
     *     $table = $db->quote_table($table);
     *
     * Objects passed to this function will be converted to strings.
     * [Database_Expression] objects will be compiled.
     * [Database_Query] objects will be compiled and converted to a sub-query.
     * All other objects will be converted using the `__toString` method.
     *
     * @param mixed $table table name or array(table, alias)
     * @return  string
     * @uses    Database::quote_identifier
     */
    public function quote_table($table): string {
        if (\is_array($table)) {
            list($table, $alias) = $table;
            $alias = \str_replace('`', '``', $alias);
        }

        if ($table instanceof Query) {
            // Create a sub-query
            $table = '(' . $table->compile($this) . ')';
        } elseif ($table instanceof Expression) {
            // Compile the expression
            $table = $table->compile($this);
        } else {
            // Convert to a string
            $table = (string)$table;

            $table = \str_replace('`', '``', $table);

            if (\strpos($table, '.') !== false) {
                $parts = \explode('.', $table);

                foreach ($parts as & $part) {
                    // Quote each of the parts
                    $part = '`' . $part . '`';
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
     * Objects passed to this function will be converted to strings.
     * [Database_Expression] objects will be compiled.
     * [Database_Query] objects will be compiled and converted to a sub-query.
     * All other objects will be converted using the `__toString` method.
     *
     * @param mixed $value any identifier
     * @return  string
     */
    public function quote_identifier($value): string {

        if (\is_array($value)) {
            list($value, $alias) = $value;
            $alias = \str_replace('`', '``', $alias);
        }

        if (\is_object($value) AND $value instanceof Query) {
            // Create a sub-query
            $value = '(' . $value->compile($this) . ')';
        } elseif ($value instanceof Expression) {
            // Compile the expression
            $value = $value->compile($this);
        } else {
            // Convert to a string
            $value = (string)$value;

            $value = \str_replace('`', '``', $value);

            if (\strpos($value, '.') !== false) {
                $parts = \explode('.', $value);

                foreach ($parts as & $part) {
                    // Quote each of the parts
                    $part = "`$part`";
                }

                $value = \implode('.', $parts);
            } else {
                $value = "`$value`";
            }
        }

        if (isset($alias)) {
            $value .= " AS `$alias`";
        }

        return $value;
    }

}
