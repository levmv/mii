<?php declare(strict_types=1);

namespace mii\util;

class Arr
{
    /**
     * Tests if an array is associative or not.
     *
     * @param array $array array to check
     */
    public static function isAssoc(array $array): bool
    {
        return !\array_is_list($array);
    }

    /**
     * Gets a value from an array using a dot separated path.
     *
     * @param mixed $array array to search
     * @param mixed $path key path string (delimiter separated) or array of keys
     * @param mixed $default default value if the path is not set
     * @param string $delimiter key path delimiter
     */
    public static function path(mixed $array, mixed $path, mixed $default = null, string $delimiter = '.'): mixed
    {
        if (!\is_array($array) && !\is_object($array) && !($array instanceof \Traversable)) {
            // This is not an array!
            return $default;
        }

        if (\is_array($path)) {
            // The path has already been separated into keys
            $keys = $path;
        } else {
            if (\array_key_exists($path, $array)) {
                // No need to do extra processing
                return $array[$path];
            }

            // Remove  delimiters and spaces
            $path = \trim((string)$path, "$delimiter ");

            // Split the keys by delimiter
            $keys = \explode($delimiter, $path);
        }

        do {
            $key = \array_shift($keys);

            /* if (\ctype_digit($key)) {
                 // Make the key an integer
                 $key = (int)$key;
             }*/

            if (isset($array[$key])) {
                if (!$keys) {
                    // Found the path requested
                    return $array[$key];
                }

                if (!\is_array($array[$key]) && !\is_iterable($array[$key])) {
                    // Unable to dig deeper
                    break;
                }
                // Dig down into the next part of the path
                $array = $array[$key];
            } else {
                // Unable to dig deeper
                break;
            }
        } while ($keys);

        // Unable to find the value requested
        return $default;
    }

    /**
     * Set a value on an array by path.
     *
     * @param array $array Array to update
     * @param string $path Path
     * @param mixed $value Value to set
     * @param string $delimiter Path delimiter
     * @see Arr::path()
     */
    public static function setPath(&$array, string $path, mixed $value, string $delimiter = '.'): void
    {
        // Split the keys by delimiter
        $keys = \explode($delimiter, $path);

        // Set current $array to innermost array path
        while (\count($keys) > 1) {
            $key = \array_shift($keys);

            if (\is_string($key) && \ctype_digit($key)) {
                // Make the key an integer
                $key = (int)$key;
            }

            if (!isset($array[$key])) {
                $array[$key] = [];
            }

            $array =& $array[$key];
        }

        // Set key on inner-most array
        $array[\array_shift($keys)] = $value;
    }


    /**
     * Retrieves multiple paths from an array. If the path does not exist in the
     * array, the default value will be added instead.
     *
     * @param array $array array to extract paths from
     * @param array $paths list of path
     * @param mixed $default default value
     */
    public static function extract($array, array $paths, mixed $default = null): array
    {
        $found = [];
        foreach ($paths as $path) {
            static::setPath($found, $path, static::path($array, $path, $default));
        }

        return $found;
    }


    /**
     * Recursively merge two or more arrays. Values in an associative array
     * overwrite previous values with the same key. Values in an indexed array
     * are appended, but only when they do not already exist in the result.
     *
     * Note that this does not work the same as [array_merge_recursive](http://php.net/array_merge_recursive)!
     *
     *     $john = array('name' => 'john', 'children' => array('fred', 'paul', 'sally', 'jane'));
     *     $mary = array('name' => 'mary', 'children' => array('jane'));
     *
     *     // John and Mary are married, merge them together
     *     $john = Arr::merge($john, $mary);
     *
     *     // The output of $john will now be:
     *     array('name' => 'mary', 'children' => array('fred', 'paul', 'sally', 'jane'))
     *
     * @param array $array1 initial array
     * @param array ...$arrays
     */
    public static function merge(array $array1, array ...$arrays): array
    {
        foreach ($arrays as $array2) {
            if (static::isAssoc($array2)) {
                foreach ($array2 as $key => $value) {
                    if (\is_array($value)
                        && isset($array1[$key])
                        && \is_array($array1[$key])
                    ) {
                        $array1[$key] = static::merge($array1[$key], $value);
                    } else {
                        $array1[$key] = $value;
                    }
                }
            } else {
                foreach ($array2 as $value) {
                    if (!\in_array($value, $array1, true)) {
                        $array1[] = $value;
                    }
                }
            }
        }

        return $array1;
    }

    /**
     * Overwrites an array with values from input arrays.
     * Keys that do not exist in the first array will not be added!
     *
     *     $a1 = array('name' => 'john', 'mood' => 'happy', 'food' => 'bacon');
     *     $a2 = array('name' => 'jack', 'food' => 'tacos', 'drink' => 'beer');
     *
     *     // Overwrite the values of $a1 with $a2
     *     $array = Arr::overwrite($a1, $a2);
     *
     *     // The output of $array will now be:
     *     array('name' => 'jack', 'mood' => 'happy', 'food' => 'tacos')
     *
     * @param array $array1 master array
     * @param array ...$arrays
     */
    public static function overwrite(array $array1, array ...$arrays): array
    {
        foreach ($arrays as $array2) {
            foreach (\array_intersect_key($array2, $array1) as $key => $value) {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }


    /**
     * Convert a multi-dimensional array into a single-dimensional array.
     *
     *     $array = array('set' => array('one' => 'something'), 'two' => 'other');
     *
     *     // Flatten the array
     *     $array = Arr::flatten($array);
     *
     *     // The array will now be
     *     ['one' => 'something', 'two' => 'other'];
     *
     * [!!] The keys of array values will be discarded.
     *
     * @param array $array array to flatten
     */
    public static function flatten($array): array
    {
        $is_assoc = static::isAssoc($array);

        $flat = [];
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $flat = \array_merge($flat, static::flatten($value));
            } elseif ($is_assoc) {
                $flat[$key] = $value;
            } else {
                $flat[] = $value;
            }
        }
        return $flat;
    }

    public static function only(array $from, array $properties): array
    {
        $result = [];

        foreach ($properties as $key => $name) {
            if (\is_int($key)) {
                $result[$name] = $from[$name];
            } elseif (\is_string($name)) {
                $result[$key] = $from[$name];
            } elseif ($name instanceof \Closure) {
                $result[$key] = $name($from[$key]);
            }
        }
        return $result;
    }


    public static function toArray($object, array $properties = [], bool $recursive = true): array
    {
        if (\is_array($object)) {
            if ($recursive) {
                foreach ($object as $key => $value) {
                    if (\is_array($value) || \is_object($value)) {
                        $object[$key] = static::toArray($value, $properties);
                    }
                }
            }
            return $object;
        }

        if (\is_object($object)) {
            $result = [];
            if (!empty($properties)) {
                foreach ($properties as $key => $name) {
                    if (\is_int($key)) {
                        $result[$name] = $object->$name;
                    } elseif (\is_string($name)) {
                        $result[$key] = $object->$name;
                    } elseif ($name instanceof \Closure) {
                        $result[$key] = $name($object);
                    }
                }

                return $recursive ? static::toArray($result, $properties) : $result;
            }

            foreach ($object as $key => $value) {
                $result[$key] = $value;
            }

            return $recursive ? static::toArray($result) : $result;
        }

        return $object;
    }
}
