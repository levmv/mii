<?php declare(strict_types=1);

namespace mii\util;

use mii\core\Exception;

class FS
{

    static public function mkdir($path, $mode = 0775, $recursive = true)
    {
        $path = \Mii::resolve($path);

        if (is_dir($path)) {
            return true;
        }
        $parent = \dirname($path);

        if ($recursive && !is_dir($parent) && $parent !== $path) {
            static::mkdir($parent, $mode, true);
        }
        try {
            if (!mkdir($path, $mode)) {
                return false;
            }
        } catch (\Exception $e) {
            if (!is_dir($path)) {
                throw new Exception("Failed to create directory \"$path\": " . $e->getMessage(), $e->getCode(), $e);
            }
        }
        try {
            return chmod($path, $mode);
        } catch (\Exception $e) {
            throw new Exception("Failed to change permissions for directory \"$path\": " . $e->getMessage(), $e->getCode(), $e);
        }
    }

}
