<?php /** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace mii\cache;

class Apcu extends Cache
{
    /**
     * Retrieve a cached value entry by id.
     *
     * @param string $id id of cache to entry
     * @param string $default default value to return if cache miss
     */
    public function get(string $id, $default = null): mixed
    {
        $data = \apcu_fetch($this->sanitizeId($id), $success);

        return $success ? $data : $default;
    }

    /**
     * Set a value to cache with id and lifetime
     *
     * @param string $id id of cache entry
     * @param mixed  $data data to set to cache
     * @param integer|null $lifetime lifetime in seconds
     */
    public function set(string $id, mixed $data, int $lifetime = null): bool
    {
        if ($lifetime === null) {
            $lifetime = $this->default_expire;
        }

        return \apcu_store($this->sanitizeId($id), $data, $lifetime);
    }

    /**
     * Delete a cache entry based on id
     *
     * @param string $id id to remove from cache
     */
    public function delete(string $id): bool
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

    protected function sanitizeId(string $id): string
    {
        return $this->prefix . $id;
    }
}
