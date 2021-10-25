<?php declare(strict_types=1);

namespace mii\cache;

use mii\core\Component;

abstract class Cache extends Component
{
    protected int $default_expire = 3600;

    protected string $prefix = '';

    protected bool $serialize = true;

    /**
     * Retrieve a cached value entry by id.
     *
     * @param string $id id of cache to entry
     * @param string $default default value to return if cache miss
     * @return  mixed
     */
    abstract public function get(string $id, $default = null): mixed;

    /**
     * Set a value to cache with id and lifetime
     *
     * @param string $id id of cache entry
     * @param string  $data data to set to cache
     * @param integer|null $lifetime lifetime in seconds
     * @return  boolean
     */
    abstract public function set(string $id, $data, int $lifetime = null): bool;

    /**
     * Delete a cache entry based on id
     *
     * @param string $id id to remove from cache
     * @return  boolean
     */
    abstract public function delete(string $id): bool;

    /**
     * Delete all cache entries.
     *
     * Beware of using this method when
     * using shared memory cache systems, as it will wipe every
     * entry within the system for all clients.
     *
     * @return  boolean
     */
    abstract public function deleteAll(): bool;

    /**
     * Replaces troublesome characters with underscores.
     *
     * @param string $id id of cache to sanitize
     * @return  string
     */
    protected function sanitizeId(string $id): string
    {
        // Change slashes and spaces to underscores
        return $this->prefix . \str_replace(['/', '\\', ' '], '_', $id);
    }
}
