<?php

namespace mii\db;

class DB
{


    /**
     * @param string $q
     * @return Result
     */
    static function select($q, array $params)
    {
        return static::query(Database::SELECT, $q, $params);
    }

    static function query($type, $q, array $params = [])
    {

        $db = Database::instance();

        if (!empty($params)) {
            // Quote all of the values
            $values = array_map([$db, 'quote'], $params);

            // Replace the values in the SQL
            $q = strtr($q, $values);
        }

        return $db->query($type, $q);
    }

    /**
     * @param string $q
     * @return Result
     */
    static function update($q, array $params)
    {
        return static::query(Database::UPDATE, $q, $params);
    }

    /**
     * @param string $q
     * @return Result
     */
    static function insert($q, array $params)
    {
        return static::query(Database::INSERT, $q, $params);
    }

    /**
     * @param string $q
     * @return Result
     */
    static function delete($q, array $params)
    {
        return static::query(Database::DELETE, $q, $params);
    }

    /**
     * @param string $value
     * @param array $params
     * @return Expression
     */
    static function expr($value, array $params = [])
    {
        return new Expression($value, $params);
    }

}