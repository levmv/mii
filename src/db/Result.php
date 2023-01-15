<?php declare(strict_types=1);

namespace mii\db;

use mii\web\Pagination;

/**
 * Database result wrapper.
 */
class Result implements \Countable, \Iterator, \ArrayAccess
{
    // Raw result resource
    protected \mysqli_result $_result;

    // Total number of rows and current row
    protected int $_total_rows = 0;
    protected int $_current_row = 0;

    // Return rows as an object or associative array
    protected ?string $asObject;

    // Parameters for __construct when using object results
    protected ?array $_object_params = null;

    protected int $_internal_row = 0;

    protected ?string $_index_by = null;

    protected Pagination $_pagination;

    /**
     * Sets the total number of rows and stores the result locally.
     *
     * @param \mysqli_result $result query result
     * @param mixed $asObject
     * @param array|null $params
     */
    public function __construct(\mysqli_result $result, ?string $asObject = null, array $params = null)
    {
        // Store the result locally
        $this->_result = $result;

        // Results as objects or associative arrays
        $this->asObject = $asObject;

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


    public function current(): mixed
    {
        assert($this->_index_by === null, "You can iterate over Result if indexBy is used");

        if ($this->_current_row !== $this->_internal_row && !$this->seek($this->_current_row)) {
            return null;
        }

        // Increment internal row for optimization assuming rows are fetched in order
        $this->_internal_row++;

        if ($this->asObject !== null) {
            // Return an object of given class name
            return $this->_result->fetch_object($this->asObject, !\is_null($this->_object_params) ?: [null, true]);
        }

        // Return an array of the row
        return $this->_result->fetch_assoc();
    }

    public function all(): array
    {
        if ($this->_index_by) {
            return $this->asObject !== null
                ? $this->index($this)
                : $this->index($this->_result->fetch_all(\MYSQLI_ASSOC));
        }

        return $this->asObject !== null
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
        // We set _index_by to null for assert() in current() to work
        // Don't know another way to use assert() for disable foreach anywhere but here (and to not duplicate all iteration code).
        $indexBy = $this->_index_by;
        $this->_index_by = null;

        if ($this->asObject !== null) {
            foreach ($rows as $row) {
                $result[$row->$indexBy] = $row;
            }

            return $result;
        }

        foreach ($rows as $row) {
            $result[$row[$indexBy]] = $row;
        }

        $this->_index_by = $indexBy;

        return $result;
    }


    public function toList(string $key, string $display, $first = null): array
    {
        $rows = [];

        if ($first !== null) {
            if (\is_array($first)) {
                $rows = $first;
            } else {
                $rows[0] = $first;
            }
        }

        if ($this->asObject !== null) {
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
     * Return all the rows as an array.
     */
    public function toArray(array $properties = []): array
    {
        $results = [];
        if (empty($properties)) {

            foreach ($this as $row) {
                $results[] = $row;
            }

            return $results;
        }

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

    public function indexBy(string $column): Result
    {
        $this->_index_by = $column;
        return $this;
    }

    /**
     * Return the named column from the current row.
     *
     * @param string $name column to get
     * @param mixed|null $default default value if the column does not exist
     */
    public function column(string $name, mixed $default = null): mixed
    {
        $row = $this->current();

        if ($this->asObject !== null) {
            if (isset($row->$name)) {
                return $row->$name;
            }
        } elseif (isset($row[$name])) {
            return $row[$name];
        }

        return $default;
    }

    public function columnValues(string $name): array
    {
        $result = [];

        if ($this->asObject !== null) {
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
     */
    public function count(): int
    {
        return $this->_total_rows;
    }

    /**
     * Implements [ArrayAccess::offsetExists], determines if row exists.
     *
     * @param int $offset
     */
    public function offsetExists($offset): bool
    {
        return ($offset >= 0 && $offset < $this->_total_rows);
    }

    /**
     * Implements [ArrayAccess::offsetGet], gets a given row.
     *
     * @param int $offset
     */
    public function offsetGet($offset): mixed
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
     * @param int $offset
     * @param mixed $value
     * @throws DatabaseException
     */
    final public function offsetSet($offset, mixed $value): void
    {
        throw new DatabaseException('Database results are read-only');
    }

    /**
     * Implements [ArrayAccess::offsetUnset], throws an error.
     *
     * [!!] You cannot modify a database result.
     *
     * @param int $offset
     * @throws DatabaseException
     */
    final public function offsetUnset($offset): void
    {
        throw new DatabaseException('Database results are read-only');
    }

    /**
     * Implements [Iterator::key], returns the current row number.
     */
    public function key(): int
    {
        return $this->_current_row;
    }

    /**
     * Implements [Iterator::next], moves to the next row.
     */
    public function next(): void
    {
        ++$this->_current_row;
    }

    /**
     * Implements [Iterator::prev], moves to the previous row.
     */
    public function prev(): static
    {
        --$this->_current_row;

        return $this;
    }

    /**
     * Implements [Iterator::rewind], sets the current row to zero.
     */
    public function rewind(): void
    {
        $this->_current_row = 0;
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


    public function setPagination(Pagination $pagination): void
    {
        $this->_pagination = $pagination;
    }

    public function pagination(): Pagination
    {
        return $this->_pagination;
    }
}
