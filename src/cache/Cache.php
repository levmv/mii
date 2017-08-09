<?php

namespace mii\cache;


use mii\core\Component;

abstract class Cache extends Component {


    protected $default_expire = 3600;

    protected $prefix = '';


    /**
     * Overload the __clone() method to prevent cloning
     *
     * @return  void
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
    protected function _sanitize_id($id) : string
    {
        // Change slashes and spaces to underscores
        return $this->prefix.str_replace(['/', '\\', ' '], '_', $id);
    }
}
