<?php

namespace mii\db;

class DB
{


    static function raw($q, array $params = []) {
        return static::query(null, $q, $params);
    }

    /**
     * @param string $q
     * @return Result
     */
    static function select($q, array $params = []) {
        return static::query(Database::SELECT, $q, $params);
    }

    /**
     * @param int
     * @param string $q
     * @param array $params
     * @return Result
     */
    static function query($type, $q, array $params = []) {

        $db = \Mii::$app->db;

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
    static function update($q, array $params = []) {
        return static::query(Database::UPDATE, $q, $params);
    }

    /**
     * @param string $q
     * @return Result
     */
    static function insert($q, array $params = []) {
        return static::query(Database::INSERT, $q, $params);
    }

    /**
     * @param string $q
     * @return Result
     */
    static function delete($q, array $params = []) {
        return static::query(Database::DELETE, $q, $params);
    }

    /**
     * @param string $value
     * @param array $params
     * @return Expression
     */
    static function expr($value, array $params = []) {
        return new Expression($value, $params);
    }


    static function begin() {
        \Mii::$app->db->begin();
    }

    static function commit() {
        \Mii::$app->db->commit();
    }

    static function rollback() {
        \Mii::$app->db->rollback();
    }


}