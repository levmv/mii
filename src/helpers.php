<?php /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

if (!\function_exists('abort')) {

    /**
     * @throws \mii\web\HttpException
     * @throws \mii\web\NotFoundHttpException
     */
    function abort(int $code = 404, string $message = ''): never
    {
        if ($code === 404) {
            throw new \mii\web\NotFoundHttpException();
        }
        throw new \mii\web\HttpException($code, $message);
    }
}

if (!\function_exists('redirect')) {
    /**
     * @param      $url
     */
    function redirect($url, bool $use_back_url = false): never
    {
        if ($use_back_url) {
            $url = \mii\util\Url::back($url);
        }
        throw new \mii\web\RedirectHttpException($url);
    }
}

if (!\function_exists('block')) {
    /**
     * @param $name string
     * @param array|null $params
     */
    function block(string $name, array $params = null): \mii\web\Block
    {
        if (!\is_null($params)) {
            return Mii::$app->blocks->get($name)->set($params);
        }
        return Mii::$app->blocks->get($name);
    }
}

if (!\function_exists('renderBlock')) {
    /**
     * @param array|null $params
     */
    function renderBlock(string $name, array $params = null): string
    {
        if (!\is_null($params)) {
            return Mii::$app->blocks
                ->get($name)
                ->set($params)
                ->render(true);
        }
        return Mii::$app->blocks->get($name)->render(true);
    }
}

if (!\function_exists('getCached')) {
    /**
     * Retrieve a cached value entry by id.
     *
     * @param string $id id of cache to entry
     * @param string $default default value to return if cache miss
     */
    function getCached(string $id, $default = null, $lifetime = null): mixed
    {
        if ($default instanceof \Closure) {
            $cached = Mii::$app->cache->get($id);
            if ($cached === null) {
                $cached = $default();

                Mii::$app->cache->set($id, $cached, $lifetime);
            }
            return $cached;
        }

        return Mii::$app->cache->get($id, $default);
    }
}

if (!\function_exists('cache')) {
    /**
     * Set a value to cache with id and lifetime
     *
     * @param string $id id of cache entry
     * @param mixed   $data data to set to cache
     * @param integer|null $lifetime lifetime in seconds
     * @return  boolean
     */
    function cache(string $id, mixed $data, int $lifetime = null)
    {
        return Mii::$app->cache->set($id, $data, $lifetime);
    }
}

if (!\function_exists('clearCache')) {
    /**
     * Delete a cache entry based on id, or delete all cache entries.
     *
     * @param string|null $id id to remove from cache
     */
    function clearCache(string $id = null): bool
    {
        if ($id === null) {
            return Mii::$app->cache->deleteAll();
        }

        return Mii::$app->cache->delete($id);
    }
}

if (!\function_exists('path')) {
    function path(string $name): string
    {
        return Mii::$paths[$name];
    }
}

if (!\function_exists('e')) {
    function e(?string $text): string
    {
        return mii\util\HTML::entities($text, false);
    }
}

if (!\function_exists('dd')) {
    /**
     * Dump and die
     */
    function dd(...$params)
    {
        if (Mii::$app instanceof \mii\web\App && Mii::$app->response->format === \mii\web\Response::FORMAT_HTML) {
            echo '<style>pre{font-size:14px;text-align:left;color:#111;overflow:auto;white-space:pre-wrap}';
            echo 'pre small{font-size:1em;color:navy;font-weight:700}';
            echo "</style><pre>\n";

            \array_map(static function ($a) {
                echo \mii\util\Debug::dump($a, 400);
            }, $params);

            echo "</pre>\n";
        } else {
            \array_map(static function ($a) {
                \var_dump($a);
            }, $params);
        }
        die;
    }
}

if (!\function_exists('config')) {
    function config(string $key, $default = null)
    {
        if (isset(Mii::$app->_config[$key])) {
            return Mii::$app->_config[$key];
        }

        $keys = \explode('.', $key);
        $array = Mii::$app->_config;

        do {
            $key = \array_shift($keys);

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

        return $default;
    }
}

if (!\function_exists('configSet')) {
    function configSet(string $key, $value)
    {
        \mii\util\Arr::setPath(Mii::$app->_config, $key, $value);
    }
}
