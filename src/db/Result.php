<?php declare(strict_types=1);

namespace mii\db;

use mii\web\Pagination;

/**
 * Database result wrapper.
 */
class Result implements \Countable, \Iterator, \SeekableIterator, \ArrayAccess
{
    // Raw result resource
    protected \mysqli_result $_result;

    // Total number of rows and current row
    protected int $_total_rows = 0;
    protected int $_current_row = 0;

    // Return rows as an object or associative array
    protected $_as_object;

    // Parameters for __construct when using object results
    protected ?array $_object_params = null;

    protected int $_internal_row = 0;

    protected $_index_by;

    protected Pagination $_pagination;

    /**
     * Sets the total number of rows and stores the result locally.
     *
     * @param mixed $result query result
     * @param mixed $as_object
     * @param array $params
     */
    public function __construct($result, $as_object = false, array $params = null)
    {
        // Store the result locally
        $this->_result = $result;

        // Results as objects or associative arrays
        $this->_as_object = $as_object;

        if ($params !== null) {
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
        if (\is_resource($this->_result)) {
            $this->_result->free();
        }
    }


    public function seek($offset)
    {
        if ($this->offsetExists($offset) && $this->_result->data_seek($offset)) {
            // Set the current row to the offset
            $this->_current_row = $this->_internal_row = $offset;

            return true;
        }

        return false;
    }


    public function current()
    {
        if ($this->_current_row !== $this->_internal_row && !$this->seek($this->_current_row)) {
            return null;
        }

        // Increment internal row for optimization assuming rows are fetched in order
        $this->_internal_row++;

        if ($this->_as_object) {
            // Return an object of given class name
            return $this->_result->fetch_object($this->_as_object, !\is_null($this->_object_params) ?: [null, true]);
        }

        // Return an array of the row
        return $this->_result->fetch_assoc();
    }

    public function all(): array
    {
        if ($this->_index_by) {
            return $this->_as_object
                ? $this->index($this)
                : $this->index($this->_result->fetch_all(\MYSQLI_ASSOC));
        }

        return $this->_as_object
            ? $this->toArray()
            : $this->_result->fetch_all(\MYSQLI_ASSOC);
    }

    public function each(\Closure $callback): self
    {
        foreach ($this as $row) {
            $callback($row);
        }

        return $this;
    }


    protected function index($rows): array
    {
        $result = [];

        if (!\is_string($this->_index_by)) {
            foreach ($rows as $row) {
                $result[\call_user_func($this->_index_by, $row)] = $row;
            }

            return $result;
        }

        if ($this->_as_object) {
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


    public function toList($key, $display, $first = null): array
    {
        $rows = [];

        if ($first) {
            if (\is_array($first)) {
                $rows = $first;
            } else {
                $rows[0] = $first;
            }
        }

        if ($this->_as_object) {
            foreach ($this as $row) {
                $rows[$row->$key] = $row->$display;
            }
            return $rows;
        }

        foreach ($this as $row) {
            $rows[$row[$key]] = $row[$display];
        }
        return $rows;
    }


    /**
     * Return all of the rows in the result as an array.
     * @param array $properties
     * @return array
     */
    public function toArray(array $properties = []): array
    {
        if (empty($properties)) {
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
                if (\is_int($key)) {
                    $result[$name] = $object->$name;
                } elseif (\is_string($name)) {
                    $result[$key] = $object->$name;
                } elseif ($name instanceof \Closure) {
                    $result[$key] = $name($object);
                }
            }
            $results[] = $result;
        }

        return $results;
    }

    public function indexBy(string $column)
    {
        $this->_index_by = $column;
        return $this;
    }

    /**
     * Return the named column from the current row.
     *
     *     // Get the "id" value
     *     $id = $result->get('id');
     *
     * @param string $name column to get
     * @param mixed  $default default value if the column does not exist
     * @return  mixed
     */
    public function column($name, $default = null)
    {
        $row = $this->current();

        if ($this->_as_object) {
            if (isset($row->$name)) {
                return $row->$name;
            }
        } elseif (isset($row[$name])) {
            return $row[$name];
        }

        return $default;
    }

    public function columnValues($name): array
    {
        $result = [];

        if ($this->_as_object) {
            foreach ($this as $row) {
                $result[] = $row->$name;
            }
            return $result;
        }

        foreach ($this as $row) {
            $result[] = $row[$name];
        }

        return $result;
    }

    public function scalar($default = 0)
    {
        $value = $this->_result->fetch_array(\MYSQLI_NUM);
        return \is_array($value) ? $value[0] : $default;
    }

    /**
     * Implements [Countable::count], returns the total number of rows.
     *
     *     echo count($result);
     *
     * @return  integer
     */
    public function count(): int
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
     * @param int $offset
     * @return  boolean
     */
    public function offsetExists($offset)
    {
        return ($offset >= 0 and $offset < $this->_total_rows);
    }

    /**
     * Implements [ArrayAccess::offsetGet], gets a given row.
     *
     *     $row = $result[10];
     *
     * @param int $offset
     * @return  mixed
     */
    public function offsetGet($offset)
    {
        if (!$this->seek($offset)) {
            return null;
        }

        return $this->current();
    }

    /**
     * Implements [ArrayAccess::offsetSet], throws an error.
     *
     * [!!] You cannot modify a database result.
     *
     * @param int   $offset
     * @param mixed $value
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
     * @param int $offset
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
    public function key(): int
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
    public function valid(): bool
    {
        return $this->offsetExists($this->_current_row);
    }


    public function setPagination($pagination)
    {
        $this->_pagination = $pagination;
    }

    public function pagination(): Pagination
    {
        return $this->_pagination;
    }
}
