<?php

namespace mii\db;

use mii\util\Arr;

/**
 * Database result wrapper.
 */

class Result implements \Countable, \Iterator, \SeekableIterator, \ArrayAccess
{

    // Executed SQL for this result
    protected $_query;

    // Raw result resource
    protected $_result;

    // Total number of rows and current row
    protected $_total_rows = 0;
    protected $_current_row = 0;

    // Return rows as an object or associative array
    protected $_as_object;

    // Parameters for __construct when using object results
    protected $_object_params = NULL;

    protected $_internal_row = 0;

    protected $_index_by;

    /**
     * Sets the total number of rows and stores the result locally.
     *
     * @param   mixed $result query result
     * @param   string $sql SQL query
     * @param   mixed $as_object
     * @param   array $params
     * @return  void
     */
    public function __construct($result, $sql, $as_object = false, array $params = NULL)
    {
        // Store the result locally
        $this->_result = $result;

        // Store the SQL locally
        $this->_query = $sql;

        // Results as objects or associative arrays
        $this->_as_object = $as_object;

        if ($params !== NULL) {
            // Object constructor params
            $this->_object_params = $params;
        }

        $this->_total_rows = $result->num_rows;
    }

    /**
     * Result destruction cleans up all open result sets.
     */
    public function __destruct()
    {
        if (is_resource($this->_result)) {
            $this->_result->free();
        }
    }


    public function seek($offset)
    {
        if ($this->offsetExists($offset) AND $this->_result->data_seek($offset)) {
            // Set the current row to the offset
            $this->_current_row = $this->_internal_row = $offset;

            return true;
        }

        return false;
    }


    public function current()
    {
        if ($this->_current_row !== $this->_internal_row AND !$this->seek($this->_current_row))
            return null;

        // Increment internal row for optimization assuming rows are fetched in order
        $this->_internal_row++;

        if ($this->_as_object) {
            // Return an object of given class name
            return $this->_result->fetch_object($this->_as_object, (array)$this->_object_params);

        } else {
            // Return an array of the row
            return $this->_result->fetch_assoc();
        }
    }

    public function all() : array
    {
        if ($this->_index_by) {

            return $this->_as_object
                ? $this->index($this)
                : $this->index($this->_result->fetch_all(MYSQLI_ASSOC));
        }

        return $this->_as_object
            ? $this->to_array()
            : $this->_result->fetch_all(MYSQLI_ASSOC);
    }


    public function index($rows) : array
    {
        $result = [];

        if (!is_string($this->_index_by)) {

            foreach ($rows as $row) {
                $result[call_user_func($this->_index_by, $row)] = $row;
            }

            return $result;

        }

        if($this->_as_object) {
            foreach ($rows as $row) {
                $key = $row->{$this->_index_by};
                $result[$key] = $row;
            }

            return $result;
        }

        foreach ($rows as $row) {

            $result[$row[$this->_index_by]] = $row;
        }

        return $result;
    }


    public function to_list($key, $display, $first = null) : array {
        $rows = [];

        if ($first) {
            if (is_array($first)) {
                $rows = $first;
            } else {
                $rows[0] = $first;
            }

        }
        foreach ($this as $row) {
            if($this->_as_object)
                $rows[$row->$key] = $row->$display;
            else
                $rows[$row[$key]] = $row[$display];

        }
        return $rows;
    }


    /**
     * Return all of the rows in the result as an array.
     *
     */
    public function to_array(array $properties = []) : array
    {
        if(empty($properties)) {
            $results = [];

            foreach ($this as $row) {
                $results[] = $row;
            }

            return $results;
        }

        $results = [];
        foreach ($this as $object) {
            $result = [];
            foreach ($properties as $key => $name) {
                if (is_int($key)) {
                    $result[$name] = $object->$name;
                } else {
                    if (is_string($name)) {
                        $result[$key] = $object->$name;
                    } elseif ($name instanceof \Closure) {
                        $result[$key] = $name($object);
                    }
                }
            }
            $results[] = $result;
        }

        return $results;
    }

    public function index_by(string $column) {
        $this->_index_by = $column;
        return $this;
    }

    /**
     * Return the named column from the current row.
     *
     *     // Get the "id" value
     *     $id = $result->get('id');
     *
     * @param   string $name column to get
     * @param   mixed $default default value if the column does not exist
     * @return  mixed
     */
    public function column($name, $default = NULL)
    {
        $row = $this->current();

        if ($this->_as_object) {
            if (isset($row->$name))
                return $row->$name;
        } else {
            if (isset($row[$name]))
                return $row[$name];
        }

        return $default;
    }

    /**
     * Implements [Countable::count], returns the total number of rows.
     *
     *     echo count($result);
     *
     * @return  integer
     */
    public function count() : int
    {
        return $this->_total_rows;
    }

    /**
     * Implements [ArrayAccess::offsetExists], determines if row exists.
     *
     *     if (isset($result[10]))
     *     {
     *         // Row 10 exists
     *     }
     *
     * @param   int $offset
     * @return  boolean
     */
    public function offsetExists($offset)
    {
        return ($offset >= 0 AND $offset < $this->_total_rows);
    }

    /**
     * Implements [ArrayAccess::offsetGet], gets a given row.
     *
     *     $row = $result[10];
     *
     * @param   int $offset
     * @return  mixed
     */
    public function offsetGet($offset)
    {
        if (!$this->seek($offset))
            return NULL;

        return $this->current();
    }

    /**
     * Implements [ArrayAccess::offsetSet], throws an error.
     *
     * [!!] You cannot modify a database result.
     *
     * @param   int $offset
     * @param   mixed $value
     * @return  void
     */
    final public function offsetSet($offset, $value)
    {
        throw new DatabaseException('Database results are read-only');
    }

    /**
     * Implements [ArrayAccess::offsetUnset], throws an error.
     *
     * [!!] You cannot modify a database result.
     *
     * @param   int $offset
     * @return  void
     */
    final public function offsetUnset($offset)
    {
        throw new DatabaseException('Database results are read-only');
    }

    /**
     * Implements [Iterator::key], returns the current row number.
     *
     *     echo key($result);
     *
     * @return  integer
     */
    public function key() : int
    {
        return $this->_current_row;
    }

    /**
     * Implements [Iterator::next], moves to the next row.
     *
     *     next($result);
     *
     * @return  $this
     */
    public function next()
    {
        ++$this->_current_row;

        return $this;
    }

    /**
     * Implements [Iterator::prev], moves to the previous row.
     *
     *     prev($result);
     *
     * @return  $this
     */
    public function prev()
    {
        --$this->_current_row;

        return $this;
    }

    /**
     * Implements [Iterator::rewind], sets the current row to zero.
     *
     *     rewind($result);
     *
     * @return  $this
     */
    public function rewind()
    {
        $this->_current_row = 0;

        return $this;
    }

    /**
     * Implements [Iterator::valid], checks if the current row exists.
     *
     * [!!] This method is only used internally.
     *
     */
    public function valid() : bool
    {
        return $this->offsetExists($this->_current_row);
    }


}
