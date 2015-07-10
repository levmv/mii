<?php

namespace mii\cache;

use mii\util\Arr;

abstract class Cache {


    /**
     * @var   string     default driver to use
     */
    public static $default = 'apc';

    /**
     * @var   Cache instances
     */
    public static $instances = [];

    /**
     * Creates a singleton of a Kohana Cache group. If no group is supplied
     * the __default__ cache group is used.
     *
     *     // Create an instance of the default group
     *     $default_group = Cache::instance();
     *
     *     // Create an instance of a group
     *     $foo_group = Cache::instance('foo');
     *
     *     // Access an instantiated group directly
     *     $foo_group = Cache::$instances['default'];
     *
     * @param   string  $group  the name of the cache group to use [Optional]
     * @return  Cache
     * @throws  CacheException
     */
    public static function instance($group = NULL)
    {
        // If there is no group supplied
        if ($group === NULL)
        {
            // Use the default setting
            $group = Cache::$default;
        }

        if (isset(Cache::$instances[$group]))
        {
            // Return the current group if initiated already
            return Cache::$instances[$group];
        }

        $config = config('cache');


        if ( ! isset($config[$group]))
        {
            throw new CacheException(
                'Failed to load Cache config for :group',
                [':group' => $group]
            );
        }

        $config = $config[$group];

        // Create a new cache type instance
        $cache_class = $config['class'];
        Cache::$instances[$group] = new $cache_class($config);

        // Return the instance
        return Cache::$instances[$group];
    }

    /**
     * @var  Config
     */

    protected $default_expire = 3600;

    protected $prefix = '';

    /**
     * Ensures singleton pattern is observed, loads the default expiry
     *
     * @param  array  $config  configuration
     */
    protected function __construct(array $config)
    {
        foreach ($config as $name => $value) {
            $this->$name = $value;
        }
    }

    /**
     * Overload the __clone() method to prevent cloning
     *
     * @return  void
     * @throws  Cache_Exception
     */
    final public function __clone()
    {
        throw new CacheException('Cloning of Cache objects is forbidden');
    }

    /**
     * Retrieve a cached value entry by id.
     *
     *     // Retrieve cache entry from default group
     *     $data = Cache::instance()->get('foo');
     *
     *     // Retrieve cache entry from default group and return 'bar' if miss
     *     $data = Cache::instance()->get('foo', 'bar');
     *
     *     // Retrieve cache entry from memcache group
     *     $data = Cache::instance('memcache')->get('foo');
     *
     * @param   string  $id       id of cache to entry
     * @param   string  $default  default value to return if cache miss
     * @return  mixed
     * @throws  CacheException
     */
    abstract public function get($id, $default = NULL);

    /**
     * Set a value to cache with id and lifetime
     *
     *     $data = 'bar';
     *
     *     // Set 'bar' to 'foo' in default group, using default expiry
     *     Cache::instance()->set('foo', $data);
     *
     *     // Set 'bar' to 'foo' in default group for 30 seconds
     *     Cache::instance()->set('foo', $data, 30);
     *
     *     // Set 'bar' to 'foo' in memcache group for 10 minutes
     *     if (Cache::instance('memcache')->set('foo', $data, 600))
     *     {
     *          // Cache was set successfully
     *          return
     *     }
     *
     * @param   string   $id        id of cache entry
     * @param   string   $data      data to set to cache
     * @param   integer  $lifetime  lifetime in seconds
     * @return  boolean
     */
    abstract public function set($id, $data, $lifetime = 3600);

    /**
     * Delete a cache entry based on id
     *
     *     // Delete 'foo' entry from the default group
     *     Cache::instance()->delete('foo');
     *
     *     // Delete 'foo' entry from the memcache group
     *     Cache::instance('memcache')->delete('foo')
     *
     * @param   string  $id  id to remove from cache
     * @return  boolean
     */
    abstract public function delete($id);

    /**
     * Delete all cache entries.
     *
     * Beware of using this method when
     * using shared memory cache systems, as it will wipe every
     * entry within the system for all clients.
     *
     *     // Delete all cache entries in the default group
     *     Cache::instance()->delete_all();
     *
     *     // Delete all cache entries in the memcache group
     *     Cache::instance('memcache')->delete_all();
     *
     * @return  boolean
     */
    abstract public function delete_all();

    /**
     * Replaces troublesome characters with underscores.
     *
     *     // Sanitize a cache id
     *     $id = $this->_sanitize_id($id);
     *
     * @param   string  $id  id of cache to sanitize
     * @return  string
     */
    protected function _sanitize_id($id)
    {
        // Change slashes and spaces to underscores
        return $this->prefix.str_replace(['/', '\\', ' '], '_', $id);
    }
}
