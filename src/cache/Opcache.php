<?php

namespace mii\cache;

use mii\util\FS;

class Opcache extends Cache
{
    public $path = '@tmp/ocache';

    public $directory_level = 1;

    public $chmode = 0775;


    public function init(array $config = []): void {

        parent::init($config);

        $this->path = \Mii::resolve($this->path);
    }

    /**
     * Retrieve a cached value entry by id.
     *
     * @param   string $id id of cache to entry
     * @param   string $default default value to return if cache miss
     * @return  mixed
     */
    public function get($id, $default = NULL) {

        $filename = $this->cache_file($id);

        @include $filename;

        if(isset($ttl) AND $ttl < time()) {
            return $default;
        }

        return isset($val) ? $val : $default;
    }

    /**
     * Set a value to cache with id and lifetime
     *
     * @param string $id id of cache entry
     * @param string $data data to set to cache
     * @param integer $lifetime lifetime in seconds
     * @return  boolean
     * @throws \mii\core\Exception
     */
    public function set($id, $data, $lifetime = NULL) {
        if ($lifetime === NULL) {
            $lifetime = $this->default_expire;
        }

        $filename = $this->cache_file($id);

        if ($this->directory_level > 0) {
            FS::mkdir(\dirname($filename), $this->chmode, true);
        }

        $val = var_export($data, true);
        // Write to temp file first to ensure atomicity
        $tmp = tempnam("/tmp", "ocache");
        if(file_put_contents($tmp, '<?php $ttl = '.(time()+$lifetime).'; $val = ' . $val . ';', LOCK_EX)
            && rename($tmp, $filename)) {

            opcache_invalidate($filename, true);
            return true;
        }

        $error = error_get_last();

        \Mii::warning("Unable to write cache file '{$filename}': {$error['message']}", 'mii');
        return false;

    }

    /**
     * Delete a cache entry based on id
     *
     * @param   string $id id to remove from cache
     * @return  boolean
     */
    public function delete($id) {
        $filename = $this->cache_file($id);
        unlink($filename);

        return opcache_invalidate($filename, true);
    }

    /**
     * Delete all cache entries.
     *
     */
    public function delete_all(): bool {
        return $this->recursive_remove($this->path);
    }


    private function recursive_remove($path) {
        $result = true;
        try {
            foreach ((new \DirectoryIterator($path)) as $fi) {
                if ($fi->isDir() && !$fi->isDot()) {
                    $result = $this->recursive_remove($fi->getPathname());
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
     * Returns the cache file path given the cache key.
     * @param string $key cache key
     * @return string the cache file path
     */
    protected function cache_file($key)
    {
        $key = sha1($key);

        if ($this->directory_level > 0) {
            $base = $this->path;
            for ($i = 0; $i < $this->directory_level; ++$i) {
                if (($prefix = substr($key, $i + $i, 2)) !== false) {
                    $base .= DIRECTORY_SEPARATOR . $prefix;
                }
            }
            return $base . DIRECTORY_SEPARATOR . $key;
        }
        return $this->path . DIRECTORY_SEPARATOR . $key;
    }


}
