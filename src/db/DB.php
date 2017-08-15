<?php

namespace mii\db;

class DB
{


    static function raw(string $q, array $params = []) {
        return static::query(null, $q, $params);
    }

    /**
     * @param string $q
     * @return Result
     */
    static function select(string $q, array $params = []) {
        return static::query(Database::SELECT, $q, $params);
    }

    /**
     * @param int
     * @param string $q
     * @param array $params
     * @return Result|int|string
     */
    static function query(?int $type, string $q, array $params = []) {

        $db = \Mii::$app->db;

        if (!empty($params)) {
            // Quote all of the values
            $values = array_map([$db, 'quote'], $params);

            // Replace the values in the SQL
            $q = strtr($q, $values);
        }

        $result = $db->query($type, $q);

        switch ($type) {
            case Database::SELECT:
                return $result;
            case Database::INSERT:
                return $db->inserted_id();
            default:
                return $db->affected_rows();
        }
    }

    /**
     * @param string $q
     * @return int
     */
    static function update(string $q, array $params = []): int {
        return static::query(Database::UPDATE, $q, $params);
    }

    /**
     * @param string $q
     * @return Result
     */
    static function insert(string $q, array $params = []) {
        return static::query(Database::INSERT, $q, $params);
    }

    /**
     * @param string $q
     * @return int
     */
    static function delete(string $q, array $params = []): int {
        return static::query(Database::DELETE, $q, $params);
    }

    /**
     * @param string $value
     * @param array $params
     * @return Expression
     */
    static function expr($value, array $params = []): Expression {
        return new Expression($value, $params);
    }


    static function begin(): void {
        \Mii::$app->db->begin();
    }

    static function commit(): void {
        \Mii::$app->db->commit();
    }

    static function rollback(): void {
        \Mii::$app->db->rollback();
    }


}