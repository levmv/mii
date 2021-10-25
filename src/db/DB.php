<?php declare(strict_types=1);

namespace mii\db;

class DB
{
    public static function raw(string $q, array $params = [])
    {
        return static::query(null, $q, $params);
    }

    /**
     * @param string $q
     * @param array  $params
     * @return Result
     * @throws DatabaseException
     */
    public static function select(string $q, array $params = [])
    {
        return static::query(Database::SELECT, $q, $params);
    }

    /**
     * @param int|null $type
     * @param string $q
     * @param array $params
     * @return Result|int|string
     * @throws DatabaseException
     */
    public static function query(?int $type, string $q, array $params = [])
    {
        $db = \Mii::$app->db;

        if (!empty($params)) {
            // Quote all of the values
            $values = \array_map([$db, 'quote'], $params);

            // Replace the values in the SQL
            $q = \strtr($q, $values);
        }

        $result = $db->query($type, $q);

        return match ($type) {
            Database::SELECT => $result,
            Database::INSERT => $db->insertedId(),
            default => $db->affectedRows(),
        };
    }

    public static function compile(string $q, array $params = []): string
    {
        $db = \Mii::$app->db;

        if (!empty($params)) {
            // Quote all the values
            $values = \array_map([$db, 'quote'], $params);

            // Replace the values in the SQL
            $q = \strtr($q, $values);
        }
        return $q;
    }


    /**
     * @param string $q
     * @param array  $params
     * @return int
     * @throws DatabaseException
     */
    public static function alter(string $q, array $params = []): int
    {
        return static::query(Database::UPDATE, $q, $params);
    }

    /**
     * @param string $q
     * @param array  $params
     * @return int
     * @throws DatabaseException
     */
    public static function update(string $q, array $params = []): int
    {
        return static::query(Database::UPDATE, $q, $params);
    }

    /**
     * @param string $q
     * @param array  $params
     * @return Result
     * @throws DatabaseException
     */
    public static function insert(string $q, array $params = [])
    {
        return static::query(Database::INSERT, $q, $params);
    }

    /**
     * @param string $q
     * @param array  $params
     * @return int
     * @throws DatabaseException
     */
    public static function delete(string $q, array $params = []): int
    {
        return static::query(Database::DELETE, $q, $params);
    }

    /**
     * @param string $value
     * @param array  $params
     * @return Expression
     */
    public static function expr(string $value, array $params = []): Expression
    {
        return new Expression($value, $params);
    }

    public static function begin(): void
    {
        \Mii::$app->db->begin();
    }

    public static function commit(): void
    {
        \Mii::$app->db->commit();
    }

    public static function rollback(): void
    {
        \Mii::$app->db->rollback();
    }
}
