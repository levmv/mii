<?php

namespace mii\db;


/**
 * Database connection/query wrapper/helper.
 *
 *
 * @copyright  (c) 2015 Lev Morozov
 * @copyright  (c) 2008-2012 Kohana Team
 */
class Database
{

    // Query types
    const SELECT = 1;
    const INSERT = 2;
    const UPDATE = 3;
    const DELETE = 4;

    /**
     * @var  string  default instance name
     */
    public static $default = 'default';

    /**
     * @var  array  Database instances
     */
    public static $instances = [];

    /**
     * @var  string  the last query executed
     */
    public $last_query;

    /**
     * @var string Character that is used to quote identifiers
     */
    protected $_identifier = '`';

    // Identifiers are escaped by repeating them
    protected $_escaped_identifier = '``';

    /**
     * @var string Instance name
     */
    protected $_instance;

    /**
     * @var \mysqli Raw server connection
     */
    protected $_connection;

    /**
     * @var array configuration array
     */
    protected $_config;


    /**
     * Stores the database configuration locally and name the instance.
     *
     * [!!] This method cannot be accessed directly, you must use [Database::instance].
     *
     * @return  void
     */
    public function __construct($name, array $config)
    {
        // Set the instance name
        $this->_instance = $name;

        // Store the config locally
        $this->_config = $config;

        if (empty($this->_config['table_prefix'])) {
            $this->_config['table_prefix'] = '';
        }
    }

    /**
     * Get a singleton Database instance. If configuration is not specified,
     * it will be loaded from the database configuration file using the same
     * group as the name.
     *
     *     // Load the default database
     *     $db = Database::instance();
     *
     *     // Create a custom configured instance
     *     $db = Database::instance('custom', $config);
     *
     * @param   string $name instance name
     * @param   array $config configuration parameters
     * @return  Database
     */
    public static function instance($name = NULL, array $config = NULL)
    {

        if ($name === NULL) {
            // Use the default instance name
            $name = Database::$default;
        }

        if (!isset(Database::$instances[$name])) {
            if ($config === NULL) {
                // Load the configuration for this database
                $config = config('database')[$name];
            }

            // Store the database instance
            Database::$instances[$name] = new Database($name, $config);
        }

        return Database::$instances[$name];
    }

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
    public function __destruct()
    {
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
    public function disconnect()
    {
        try {
            // Database is assumed disconnected
            $status = true;

            if (is_resource($this->_connection)) {
                if ($status = $this->_connection->close()) {
                    // Clear the connection
                    $this->_connection = NULL;

                    // Clear the instance
                    unset(Database::$instances[$this->_instance]);
                }
            }
        } catch (\Exception $e) {
            // Database is probably not disconnected
            $status = !is_resource($this->_connection);
        }

        return $status;
    }

    /**
     * Returns the database instance name.
     *
     *     echo (string) $db;
     *
     * @return  string
     */
    public function __toString()
    {
        return $this->_instance;
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
     * @param   integer $type Database::SELECT, Database::INSERT, etc
     * @param   string $sql SQL query
     * @param   mixed $as_object result object class string, TRUE for stdClass, FALSE for assoc array
     * @param   array $params object construct parameters for result class
     * @return  Result   Result for SELECT queries
     * @return  array    list (insert id, row count) for INSERT queries
     * @return  integer  number of affected rows for all other queries
     */
    public function query($type, $sql, $as_object = false, array $params = NULL)
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        if (MII_PROF) {
            // Benchmark this query for the current instance
            $benchmark = \mii\util\Profiler::start("Database ({$this->_instance})", $sql);
        }

        // Execute the query
        if (($result = $this->_connection->query($sql)) === false) {
            if (MII_PROF) {
                // This benchmark is worthless
                \mii\util\Profiler::delete($benchmark);
            }

            throw new DatabaseException(':error [ :query ]', [
                ':error' => $this->_connection->error,
                ':query' => $sql
            ], $this->_connection->errno);
        }

        if (MII_PROF) {
            \mii\util\Profiler::stop($benchmark);
        }

        // Set the last query
        $this->last_query = $sql;

        if ($type === Database::SELECT) {

            // Return an iterator of results
            return new Result($result, $sql, $as_object, $params);
        } elseif ($type === Database::INSERT) {
            // Return a list of insert id and rows created
            return [
                $this->_connection->insert_id,
                $this->_connection->affected_rows,
            ];
        } else {
            // Return the number of rows affected
            return $this->_connection->affected_rows;
        }
    }

    /**
     * Connect to the database. This is called automatically when the first
     * query is executed.
     *
     *     $db->connect();
     *
     * @throws  DatabaseException
     * @return  void
     */
    public function connect()
    {
        if ($this->_connection)
            return;

        // Extract the connection parameters, adding required variables
        extract($this->_config['connection'] + [
                'database' => '',
                'hostname' => '',
                'username' => '',
                'password' => '',
                'socket'   => '',
                'port'     => 3306,
            ]);

        // Prevent this information from showing up in traces
        unset($this->_config['connection']['username'], $this->_config['connection']['password']);


        try {
            $this->_connection = mysqli_connect($hostname, $username, $password, $database, $port, $socket);
        } catch (\Exception $e) {
            // No connection exists
            $this->_connection = NULL;

            throw new DatabaseException(':error',
                [':error' => $e->getMessage()],
                $e->getCode());
        }

        if (!empty($this->_config['charset'])) {
            // Set the character set
            $this->set_charset($this->_config['charset']);
        }

        if (!empty($this->_config['connection']['variables'])) {
            // Set session variables
            $variables = [];

            foreach ($this->_config['connection']['variables'] as $var => $val) {
                $variables[] = 'SESSION ' . $var . ' = ' . $this->quote($val);
            }

            $this->_connection->query('SET ' . implode(', ', $variables));
        }

    }

    /**
     * Set the connection character set. This is called automatically by [Database::connect].
     *
     *     $db->set_charset('utf8');
     *
     * @throws  DatabaseException
     * @param   string $charset character set name
     * @return  void
     */
    public function set_charset($charset)
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        $status = $this->_connection->set_charset($charset);

        if ($status === false) {
            throw new DatabaseException(':error', [':error' => $this->_connection->error], $this->_connection->errno);
        }
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
     * @param   mixed $value any value to quote
     * @return  string
     * @uses    Database::escape
     */
    public function quote($value)
    {
        if ($value === NULL) {
            return 'NULL';
        } elseif ($value === true) {
            return "'1'";
        } elseif ($value === false) {
            return "'0'";
        } elseif (is_int($value)) {
            return (int)$value;
        } elseif (is_float($value)) {
            // Convert to non-locale aware float to prevent possible commas
            return sprintf('%F', $value);
        } elseif (is_array($value)) {
            return '(' . implode(', ', array_map([$this, __FUNCTION__], $value)) . ')';
        } elseif (is_object($value)) {
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
     * @param   string $value value to quote
     * @return  string
     */
    public function escape($value)
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        if (($value = $this->_connection->real_escape_string((string)$value)) === false) {
            throw new DatabaseException(':error', [
                ':error' => $this->_connection->error,
            ], $this->_connection->errno);
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
    public function begin($mode = NULL)
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        if ($mode AND !$this->_connection->query("SET TRANSACTION ISOLATION LEVEL $mode")) {
            throw new DatabaseException(':error', [
                ':error' => $this->_connection->error
            ], $this->_connection->errno);
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
    public function commit()
    {
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
    public function rollback()
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        return (bool)$this->_connection->query('ROLLBACK');
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
     * @param   mixed $column column name or array(column, alias)
     * @return  string
     * @uses    Database::quote_identifier
     * @uses    Database::table_prefix
     */
    public function quote_column($column)
    {

        if (is_array($column)) {
            list($column, $alias) = $column;
            $alias = str_replace($this->_identifier, $this->_escaped_identifier, $alias);
        }

        if ($column instanceof Query) {
            // Create a sub-query
            $column = '(' . $column->compile($this) . ')';
        } elseif ($column instanceof Expression) {
            // Compile the expression
            $column = $column->compile($this);
        } else {
            // Convert to a string
            $column = (string)$column;

            $column = str_replace($this->_identifier, $this->_escaped_identifier, $column);

            if ($column === '*') {
                return $column;
            } elseif (strpos($column, '.') !== false) {
                $parts = explode('.', $column);

                if ($prefix = $this->table_prefix()) {
                    // Get the offset of the table name, 2nd-to-last part
                    $offset = count($parts) - 2;

                    // Add the table prefix to the table name
                    $parts[$offset] = $prefix . $parts[$offset];
                }

                foreach ($parts as & $part) {
                    if ($part !== '*') {
                        // Quote each of the parts
                        $part = $this->_identifier . $part . $this->_identifier;
                    }
                }

                $column = implode('.', $parts);
            } else {
                $column = $this->_identifier . $column . $this->_identifier;
            }
        }

        if (isset($alias)) {
            $column .= ' AS ' . $this->_identifier . $alias . $this->_identifier;
        }

        return $column;
    }

    /**
     * Return the table prefix defined in the current configuration.
     *
     *     $prefix = $db->table_prefix();
     *
     * @return  string
     */
    public function table_prefix()
    {
        return $this->_config['table_prefix'];
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
     * @param   mixed $table table name or array(table, alias)
     * @return  string
     * @uses    Database::quote_identifier
     * @uses    Database::table_prefix
     */
    public function quote_table($table)
    {
        // Identifiers are escaped by repeating them
        $escaped_identifier = $this->_identifier . $this->_identifier;

        if (is_array($table)) {
            list($table, $alias) = $table;
            $alias = str_replace($this->_identifier, $escaped_identifier, $alias);
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

            $table = str_replace($this->_identifier, $escaped_identifier, $table);

            if (strpos($table, '.') !== false) {
                $parts = explode('.', $table);

                if ($prefix = $this->table_prefix()) {
                    // Get the offset of the table name, last part
                    $offset = count($parts) - 1;

                    // Add the table prefix to the table name
                    $parts[$offset] = $prefix . $parts[$offset];
                }

                foreach ($parts as & $part) {
                    // Quote each of the parts
                    $part = $this->_identifier . $part . $this->_identifier;
                }

                $table = implode('.', $parts);
            } else {
                // Add the table prefix
                $table = $this->_identifier . $this->table_prefix() . $table . $this->_identifier;
            }
        }

        if (isset($alias)) {
            // Attach table prefix to alias
            $table .= ' AS ' . $this->_identifier . $this->table_prefix() . $alias . $this->_identifier;
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
     * @param   mixed $value any identifier
     * @return  string
     */
    public function quote_identifier($value)
    {
        // Identifiers are escaped by repeating them
        $escaped_identifier = $this->_identifier . $this->_identifier;

        if (is_array($value)) {
            list($value, $alias) = $value;
            $alias = str_replace($this->_identifier, $escaped_identifier, $alias);
        }

        if ($value instanceof Query) {
            // Create a sub-query
            $value = '(' . $value->compile($this) . ')';
        } elseif ($value instanceof Expression) {
            // Compile the expression
            $value = $value->compile($this);
        } else {
            // Convert to a string
            $value = (string)$value;

            $value = str_replace($this->_identifier, $escaped_identifier, $value);

            if (strpos($value, '.') !== false) {
                $parts = explode('.', $value);

                foreach ($parts as & $part) {
                    // Quote each of the parts
                    $part = $this->_identifier . $part . $this->_identifier;
                }

                $value = implode('.', $parts);
            } else {
                $value = $this->_identifier . $value . $this->_identifier;
            }
        }

        if (isset($alias)) {
            $value .= ' AS ' . $this->_identifier . $alias . $this->_identifier;
        }

        return $value;
    }

}
