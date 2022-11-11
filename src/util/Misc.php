<?php declare(strict_types=1);

namespace mii\util;

use mii\db\DB;
use mii\db\ORM;
use mii\db\SelectQuery;

class Misc
{

    public static function sortValue(ORM $model, string $fieldName, string $parentField = null) {
        if ($parentField) {
            $value = (new SelectQuery)
                ->select([DB::expr('MAX(' . $fieldName . ')'), $fieldName])
                ->from($model::table())
                ->where($parentField, '=', $model->get($parentField))
                ->one();

        } else {
            $value = (new SelectQuery)
                ->select([DB::expr('MAX(' . $fieldName . ')'), $fieldName])
                ->from($model::table())
                ->one();

        }
        if ($value) {
            $value = $value[$fieldName] ?: 0;
        }

        return $value + 1;
    }


    public static function mkdir($path, $mode = 0775, $recursive = true): bool
    {
        $path = \Mii::resolve($path);

        if (\is_dir($path)) {
            return true;
        }
        $parent = \dirname($path);

        if ($recursive && !\is_dir($parent) && $parent !== $path) {
            static::mkdir($parent, $mode);
        }
        try {
            if (!\mkdir($path, $mode)) {
                return false;
            }
        } catch (\Exception $e) {
            if (!\is_dir($path)) {
                throw new \RuntimeException("Failed to create directory \"$path\": " . $e->getMessage(), $e->getCode(), $e);
            }
        }
        try {
            return \chmod($path, $mode);
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to change permissions for directory \"$path\": " . $e->getMessage(), $e->getCode(), $e);
        }
    }


    public static function getPercentile(int $percentile, array $array)
    {
        sort($array);
        $index = ($percentile / 100) * (count($array) - 1);

        return $index == floor($index)
            ? ($array[$index - 1] + $array[$index]) / 2
            : $array[floor($index)];
    }
}
