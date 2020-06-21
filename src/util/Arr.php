<?php declare(strict_types=1);

namespace mii\util;

class Arr
{
    /**
     * Tests if an array is associative or not.
     *
     *     // Returns true
     *     Arr::isAssoc(array('username' => 'john.doe'));
     *
     *     // Returns false
     *     Arr::isAssoc('foo', 'bar');
     *
     * @param array $array array to check
     * @return  boolean
     */
    public static function isAssoc(array $array)
    {
        // Keys of the array
        $keys = \array_keys($array);

        // If the array keys of the keys match the keys, then the array must
        // not be associative (e.g. the keys array looked like {0:0, 1:1...}).
        return \array_keys($keys) !== $keys;
    }

    /**
     * Gets a value from an array using a dot separated path.
     *
     *     // Get the value of $array['foo']['bar']
     *     $value = Arr::path($array, 'foo.bar');
     *
     * @param mixed  $array array to search
     * @param mixed  $path key path string (delimiter separated) or array of keys
     * @param mixed  $default default value if the path is not set
     * @param string $delimiter key path delimiter
     * @return  mixed
     */
    public static function path($array, $path, $default = null, $delimiter = '.')
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
            $path = \trim($path, "{$delimiter} ");

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
     * @param array  $array Array to update
     * @param string $path Path
     * @param mixed  $value Value to set
     * @param string $delimiter Path delimiter
     * @see Arr::path()
     */
    public static function setPath(&$array, $path, $value, $delimiter = '.'): void
    {

        // The path has already been separated into keys
        $keys = $path;
        if (!\is_array($path)) {
            // Split the keys by delimiter
            $keys = \explode($delimiter, $path);
        }

        // Set current $array to inner-most array path
        while (\count($keys) > 1) {
            $key = \array_shift($keys);

            if (\is_string($key) && \ctype_digit($key)) {
                // Make the key an integer
                $key = (int) $key;
            }

            if (!isset($array[$key])) {
                $array[$key] = [];
            }

            $array =&$array[$key];
        }

        // Set key on inner-most array
        $array[\array_shift($keys)] = $value;
    }


    /**
     * Retrieves multiple paths from an array. If the path does not exist in the
     * array, the default value will be added instead.
     *
     *     // Get the values "username", "password" from $_POST
     *     $auth = Arr::extract($_POST, array('username', 'password'));
     *
     *     // Get the value "level1.level2a" from $data
     *     $data = array('level1' => array('level2a' => 'value 1', 'level2b' => 'value 2'));
     *     Arr::extract($data, array('level1.level2a', 'password'));
     *
     * @param array $array array to extract paths from
     * @param array $paths list of path
     * @param mixed $default default value
     * @return  array
     */
    public static function extract($array, array $paths, $default = null)
    {
        $found = [];
        foreach ($paths as $path) {
            static::setPath($found, $path, static::path($array, $path, $default));
        }

        return $found;
    }

    /**
     * Recursive version of [array_map](http://php.net/array_map), applies one or more
     * callbacks to all elements in an array, including sub-arrays.
     *
     *     // Apply "strip_tags" to every element in the array
     *     $array = Arr::map('strip_tags', $array);
     *
     *     // Apply $this->filter to every element in the array
     *     $array = Arr::map(array(array($this,'filter')), $array);
     *
     *     // Apply strip_tags and $this->filter to every element
     *     $array = Arr::map(array('strip_tags',array($this,'filter')), $array);
     *
     * [!!] Because you can pass an array of callbacks, if you wish to use an array-form callback
     * you must nest it in an additional array as above. Calling Arr::map(array($this,'filter'), $array)
     * will cause an error.
     * [!!] Unlike `array_map`, this method requires a callback and will only map
     * a single array.
     *
     * @param mixed $callbacks array of callbacks to apply to every element in the array
     * @param array $array array to map
     * @param array $keys array of keys to apply to
     * @return  array
     */
    public static function map($callbacks, $array, $keys = null)
    {
        foreach ($array as $key => $val) {
            if (\is_array($val)) {
                $array[$key] = static::map($callbacks, $array[$key]);
            } elseif (!\is_array($keys) || \in_array($key, $keys)) {
                if (\is_array($callbacks)) {
                    foreach ($callbacks as $cb) {
                        $array[$key] = \call_user_func($cb, $array[$key]);
                    }
                } else {
                    $array[$key] = \call_user_func($callbacks, $array[$key]);
                }
            }
        }

        return $array;
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
     * @param array $array2,... array to merge
     * @return  array
     */
    public static function merge($array1, $array2)
    {
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

        if (\func_num_args() > 2) {
            foreach (\array_slice(\func_get_args(), 2) as $array2) {
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
     * @param array $array2 input arrays that will overwrite existing values
     * @return  array
     */
    public static function overwrite($array1, $array2)
    {
        foreach (\array_intersect_key($array2, $array1) as $key => $value) {
            $array1[$key] = $value;
        }

        if (\func_num_args() > 2) {
            foreach (\array_slice(\func_get_args(), 2) as $array2) {
                foreach (\array_intersect_key($array2, $array1) as $key => $value) {
                    $array1[$key] = $value;
                }
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
     *     array('one' => 'something', 'two' => 'other');
     *
     * [!!] The keys of array values will be discarded.
     *
     * @param array $array array to flatten
     * @return  array
     */
    public static function flatten($array)
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


    public static function toArray($object, array $properties = [], bool $recursive = true) : array
    {
        if (\is_array($object)) {
            if ($recursive) {
                foreach ($object as $key => $value) {
                    if (\is_array($value) || \is_object($value)) {
                        $object[$key] = static::toArray($value, $properties, true);
                    }
                }
            }
            return $object;
        }

        if (\is_object($object)) {
            if (!empty($properties)) {
                $result = [];
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

            $result = [];
            foreach ($object as $key => $value) {
                $result[$key] = $value;
            }

            return $recursive ? static::toArray($result) : $result;
        }

        return $object;
    }
}
