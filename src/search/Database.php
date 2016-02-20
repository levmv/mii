<?php

namespace mii\search;


use mii\db\DatabaseException;
use mii\db\Expression;

class Database extends \mii\db\Database
{

    protected $escape_chars = array(
        '\\' => '\\\\',
        '(' => '\(',
        ')' => '\)',
        '|' => '\|',
        '-' => '\-',
        '!' => '\!',
        '@' => '\@',
        '~' => '\~',
        '"' => '\"',
        '&' => '\&',
        '/' => '\/',
        '^' => '\^',
        '$' => '\$',
        '=' => '\=',
        '<' => '\<',
    );


    public function query($type, $sql, $as_object = false, array $params = NULL) {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        $benchmark = false;
        if (config('profiling')) {
            // Benchmark this query for the current instance
            $benchmark = \mii\util\Profiler::start("Sphinx ({$this->_instance})", $sql);
        }

        // Execute the query
        if (($result = $this->_connection->query($sql)) === false) {
            if ($benchmark) {
                // This benchmark is worthless
                \mii\util\Profiler::delete($benchmark);
            }

            throw new DatabaseException(':error [ :query ]', [
                ':error' => $this->_connection->error,
                ':query' => $sql
            ], $this->_connection->errno);
        }

        if ($benchmark) {
            \mii\util\Profiler::stop($benchmark);
        }

        // Set the last query
        $this->last_query = $sql;

        if ($type === Database::SELECT) {

            return $result->fetch_all(MYSQLI_ASSOC);
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


    public function quote_index($index) {
        if (is_array($index)) {
            list($index, $alias) = $index;
            $alias = str_replace('`', '``', $alias);
        }

        if ($index instanceof Expression) {
            // Compile the expression
            $index = $index->compile($this);
        } else {
            // Convert to a string
            $index = (string)$index;
            $index = str_replace('`', '``', $index);
            $index = '`' . $index . '`';
        }

        if (isset($alias)) {
            // Attach table prefix to alias
            $index .= ' AS ' . '`' . $alias . '`';
        }

        return $index;
    }

    public function escape_match($string) {
        if ($string instanceof Expression) {
            return $string->value();
        }
        return mb_strtolower(str_replace(array_keys($this->escape_chars), array_values($this->escape_chars), $string), 'utf8');
    }

}