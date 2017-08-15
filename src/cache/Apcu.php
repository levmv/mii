<?php

namespace mii\cache;

class Apcu extends Cache
{

    /**
     * Check for existence of the APC extension This method cannot be invoked externally. The driver must
     * be instantiated using the `Cache::instance()` method.
     *
     * @param  array $config configuration
     * @throws CacheException
     */
    public function init(array $config = []): void {

        if (!extension_loaded('apcu')) {
            throw new CacheException('PHP APC extension is not available.');
        }

        parent::init($config);
    }

    /**
     * Retrieve a cached value entry by id.
     *
     *     // Retrieve cache entry from apc group
     *     $data = Cache::instance('apc')->get('foo');
     *
     *     // Retrieve cache entry from apc group and return 'bar' if miss
     *     $data = Cache::instance('apc')->get('foo', 'bar');
     *
     * @param   string $id id of cache to entry
     * @param   string $default default value to return if cache miss
     * @return  mixed
     * @throws  CacheException
     */
    public function get($id, $default = NULL) {
        $data = \apcu_fetch($this->_sanitize_id($id), $success);

        return $success ? $data : $default;
    }

    /**
     * Set a value to cache with id and lifetime
     *
     *     $data = 'bar';
     *
     *     // Set 'bar' to 'foo' in apc group, using default expiry
     *     Cache::instance('apc')->set('foo', $data);
     *
     *     // Set 'bar' to 'foo' in apc group for 30 seconds
     *     Cache::instance('apc')->set('foo', $data, 30);
     *
     * @param   string $id id of cache entry
     * @param   string $data data to set to cache
     * @param   integer $lifetime lifetime in seconds
     * @return  boolean
     */
    public function set($id, $data, $lifetime = NULL) {
        if ($lifetime === NULL) {
            $lifetime = $this->default_expire;
        }

        return \apcu_store($this->_sanitize_id($id), $data, $lifetime);
    }

    /**
     * Delete a cache entry based on id
     *
     *     // Delete 'foo' entry from the apc group
     *     Cache::instance('apc')->delete('foo');
     *
     * @param   string $id id to remove from cache
     * @return  boolean
     */
    public function delete($id) {
        return \apcu_delete($this->_sanitize_id($id));
    }

    /**
     * Delete all cache entries.
     *
     * Beware of using this method when
     * using shared memory cache systems, as it will wipe every
     * entry within the system for all clients.
     *
     *     // Delete all cache entries in the apc group
     *     Cache::instance('apc')->delete_all();
     *
     */
    public function delete_all(): bool {
        return \apcu_clear_cache();
    }

    /**
     * Increments a given value by the step value supplied.
     * Useful for shared counters and other persistent integer based
     * tracking.
     *
     * @param   string $id of cache entry to increment
     * @param   int $step value to increment by
     * @return  integer
     * @return  boolean
     */
    public function increment($id, $step = 1) {
        return \apcu_inc($id, $step);
    }

    /**
     * Decrements a given value by the step value supplied.
     * Useful for shared counters and other persistent integer based
     * tracking.
     *
     * @param   string $id of cache entry to decrement
     * @param   int $step value to decrement by
     * @return  integer
     * @return  boolean
     */
    public function decrement($id, $step = 1) {
        return \apcu_dec($id, $step);
    }


    protected function _sanitize_id($id): string {
        return $this->prefix . $id;
    }
}
