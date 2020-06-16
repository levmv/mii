<?php declare(strict_types=1);

namespace mii\cache;

use mii\util\FS;

class File extends Cache
{
    public $path = '@tmp/cache';

    public $directory_level = 1;

    public $chmode = 0775;


    public function init(array $config = []): void
    {
        parent::init($config);

        $this->path = \Mii::resolve($this->path);
        if (!is_dir($this->path)) {
            FS::mkdir($this->path, 0777);
        }
    }

    /**
     * Retrieve a cached value entry by id.
     *
     * @param string $id id of cache to entry
     * @param string $default default value to return if cache miss
     * @return  mixed
     */
    public function get($id, $default = null)
    {
        $filename = $this->cacheFile($id);

        if (@\file_exists($filename) && @\filemtime($filename) > time()) {
            $fp = @\fopen($filename, 'r');
            if ($fp !== false) {
                \flock($fp, LOCK_SH);
                $value = stream_get_contents($fp);
                \flock($fp, LOCK_UN);
                \fclose($fp);

                return $this->serialize ? \unserialize($value) : $value;
            }
        }

        return $default;
    }

    /**
     * Set a value to cache with id and lifetime
     *
     * @param string  $id id of cache entry
     * @param string  $data data to set to cache
     * @param integer $lifetime lifetime in seconds
     * @return  boolean
     */
    public function set($id, $data, $lifetime = null)
    {
        if ($lifetime === null) {
            $lifetime = $this->default_expire;
        }

        $filename = $this->cacheFile($id);

        if ($this->directory_level > 0) {
            FS::mkdir(\dirname($filename), $this->chmode);
        }
        if (\file_put_contents($filename, $this->serialize ? \serialize($data) : $data, LOCK_EX) !== false) {
            if ($this->chmode !== null) {
                \chmod($filename, $this->chmode);
            }
            if ($lifetime <= 0) {
                $lifetime = 60 * 60 * 24 * 7;
            }
            return touch($filename, $lifetime + time());
        }
        $error = error_get_last();

        \Mii::warning("Unable to write cache file '{$filename}': {$error['message']}", 'mii');
        return false;
    }

    /**
     * Delete a cache entry based on id
     *
     * @param string $id id to remove from cache
     * @return  boolean
     */
    public function delete($id)
    {
        return unlink($this->cacheFile($id));
    }

    /**
     * Delete all cache entries.
     *
     */
    public function deleteAll(): bool
    {
        return $this->recursiveRemove($this->path);
    }


    protected function recursiveRemove($path): bool
    {
        $result = true;
        try {
            foreach ((new \DirectoryIterator($path)) as $fi) {
                if ($fi->isDir() && !$fi->isDot()) {
                    $result = $this->recursiveRemove($fi->getPathname());
                    rmdir($fi->getPathname());
                }

                if ($fi->isFile()) {
                    unlink($fi->getPathname());
                }
            }
        } catch (\Throwable $t) {
            \Mii::warning("Unable to clear cache: {$t->getMessage()}", 'mii');
            return false;
        }
        return $result;
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
    }


    /**
     * Returns the cache file path given the cache key.
     * @param string $key cache key
     * @return string the cache file path
     */
    protected function cacheFile(string $key): string
    {
        $key = \sha1($key);

        if ($this->directory_level > 0) {
            $base = $this->path;
            for ($i = 0; $i < $this->directory_level; ++$i) {
                if (($prefix = \substr($key, $i + $i, 2)) !== false) {
                    $base .= DIRECTORY_SEPARATOR . $prefix;
                }
            }
            return $base . DIRECTORY_SEPARATOR . $key;
        }
        return $this->path . DIRECTORY_SEPARATOR . $key;
    }
}
