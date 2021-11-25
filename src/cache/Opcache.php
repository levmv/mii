<?php /** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace mii\cache;

use mii\core\Exception;
use mii\util\FS;

class Opcache extends File
{
    public string $path = '@tmp/ocache';

    public function init(array $config = []): void
    {
        parent::init($config);

        $this->path = \Mii::resolve($this->path);
    }

    /**
     * Retrieve a cached value entry by id.
     *
     * @param string $id id of cache to entry
     * @param string $default default value to return if cache miss
     * @return  mixed
     * @noinspection IssetArgumentExistenceInspection
     * @noinspection PhpMixedReturnTypeCanBeReducedInspection
     */
    public function get(string $id, $default = null): mixed
    {
        $filename = $this->cacheFile($id);

        @include $filename;

        if (isset($ttl) && $ttl < \time()) {
            return $default;
        }

        return $val ?? $default;
    }

    /**
     * Set a value to cache with id and lifetime
     *
     * @param string $id id of cache entry
     * @param string  $data data to set to cache
     * @param integer|null $lifetime lifetime in seconds
     * @return  boolean
     * @throws Exception
     */
    public function set(string $id, $data, int $lifetime = null): bool
    {
        if ($lifetime === null) {
            $lifetime = $this->default_expire;
        }

        $filename = $this->cacheFile($id);

        if ($this->directory_level > 0) {
            FS::mkdir(\dirname($filename), $this->chmode, true);
        }

        $val = \var_export($data, true);
        // Write to temp file first to ensure atomicity
        $tmp = \tempnam('/tmp', 'ocache');
        if (\file_put_contents($tmp, '<?php $ttl = ' . (\time() + $lifetime) . '; $val = ' . $val . ';', \LOCK_EX)
            && \rename($tmp, $filename)) {
            \opcache_invalidate($filename, true);
            return true;
        }

        $error = \error_get_last();

        \Mii::warning("Unable to write cache file '$filename': {$error['message']}", 'mii');
        return false;
    }

    /**
     * Delete a cache entry based on id
     *
     * @param string $id id to remove from cache
     * @return  boolean
     */
    public function delete(string $id): bool
    {
        $filename = $this->cacheFile($id);
        \unlink($filename);

        return \opcache_invalidate($filename, true);
    }
}
