<?php declare(strict_types=1);

namespace mii\db;

class DB
{
    public static function raw(string $q, array $params = []): Result|int|string
    {
        return static::query(null, $q, $params);
    }

    public static function select(string $q, array $params = []): Result
    {
        return static::query(Database::SELECT, $q, $params);
    }

    /**
     * @throws DatabaseException
     */
    public static function query(?int $type, string $q, array $params = []): Result|int|string
    {
        $db = \Mii::$app->db;

        if (!empty($params)) {
            // Quote all the values
            $values = \array_map($db->quote(...), $params);

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
            $values = \array_map($db->quote(...), $params);

            // Replace the values in the SQL
            $q = \strtr($q, $values);
        }
        return $q;
    }


    /**
     * @throws DatabaseException
     */
    public static function alter(string $q, array $params = []): int
    {
        return static::query(Database::UPDATE, $q, $params);
    }

    /**
     * @throws DatabaseException
     */
    public static function update(string $q, array $params = []): int
    {
        return static::query(Database::UPDATE, $q, $params);
    }

    /**
     * @throws DatabaseException
     */
    public static function insert(string $q, array $params = []): Result|int|string
    {
        return static::query(Database::INSERT, $q, $params);
    }

    /**
     * @throws DatabaseException
     */
    public static function delete(string $q, array $params = []): int
    {
        return static::query(Database::DELETE, $q, $params);
    }

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
