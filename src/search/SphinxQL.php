<?php

namespace mii\search;

use mii\db\Expression;

class SphinxQL
{

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
    static function query($type, $q, array $params = [], $db = null) {

        if($db === null) {
            $db = \Mii::$app->sphinx;
        }


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
     * @return Array
     */
    static function update($q, array $params = []) {
        return static::query(Database::UPDATE, $q, $params);
    }

    /**
     * @param string $q
     * @return int
     */
    static function insert($q, array $params = []) {
        return static::query(Database::INSERT, $q, $params);
    }

    /**
     * @param string $q
     * @return int
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


    static function meta($like = null) {

        $sql = 'SHOW META';

        if($like !== null) {
            $sql .= ' LIKE '.$like;
        }

        $db_result =  static::query(Database::SELECT, $sql);

        $result = [];
        foreach($db_result as $value) {
            $result[$value['Variable_name']] = $value['Value'];
        }
        return $result;
    }

}