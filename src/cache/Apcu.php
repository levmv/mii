<?php declare(strict_types=1);

namespace mii\cache;

class Apcu extends Cache
{
    /**
     * Retrieve a cached value entry by id.
     *
     * @param string $id id of cache to entry
     * @param string $default default value to return if cache miss
     * @return  mixed
     */
    public function get($id, $default = NULL)
    {
        $data = \apcu_fetch($this->sanitizeId($id), $success);

        return $success ? $data : $default;
    }

    /**
     * Set a value to cache with id and lifetime
     *
     * @param string  $id id of cache entry
     * @param string  $data data to set to cache
     * @param integer $lifetime lifetime in seconds
     * @return  boolean
     */
    public function set($id, $data, $lifetime = NULL)
    {
        if ($lifetime === NULL) {
            $lifetime = $this->default_expire;
        }

        return \apcu_store($this->sanitizeId($id), $data, $lifetime);
    }

    /**
     * Delete a cache entry based on id
     *
     * @param string $id id to remove from cache
     * @return  boolean
     */
    public function delete($id)
    {
        return \apcu_delete($this->sanitizeId($id));
    }

    /**
     * Delete all cache entries.
     *
     * Beware of using this method when
     * using shared memory cache systems, as it will wipe every
     * entry within the system for all clients.
     *
     */
    public function deleteAll(): bool
    {
        return \apcu_clear_cache();
    }

    /**
     * Increments a given value by the step value supplied.
     * Useful for shared counters and other persistent integer based
     * tracking.
     *
     * @param string $id of cache entry to increment
     * @param int    $step value to increment by
     * @return  integer
     * @return  boolean
     */
    public function increment($id, $step = 1)
    {
        return \apcu_inc($id, $step);
    }

    /**
     * Decrements a given value by the step value supplied.
     * Useful for shared counters and other persistent integer based
     * tracking.
     *
     * @param string $id of cache entry to decrement
     * @param int    $step value to decrement by
     * @return  integer
     * @return  boolean
     */
    public function decrement($id, $step = 1)
    {
        return \apcu_dec($id, $step);
    }


    protected function sanitizeId($id): string
    {
        return $this->prefix . $id;
    }
}
