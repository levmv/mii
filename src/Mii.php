<?php

use mii\Log\Logger;

defined('MII_START_TIME') or define('MII_START_TIME', microtime(true));
defined('MII_START_MEMORY') or define('MII_START_MEMORY', memory_get_usage());



class Mii {

    const VERSION = '1.2.7';

    const CODENAME = 'τ Ceti';

    /**
     * @var \mii\web\App|\mii\console\App;
     */
    public static $app;

    /**
     * @var \mii\core\Container
     */
    public static $container;

    public static $paths = [
        'mii' => __DIR__
    ];

    public static function get_path(string $name) : string {
        return static::$paths[$name];
    }


    public static function set_path($name, $value = null) : void {
        if(is_array($name)) {
            static::$paths = array_replace(static::$paths, $name);
        } else {
            static::$paths[$name] = $value;
        }
    }


    public static function resolve(string $path) : string {
        if (strncmp($path, '@', 1)) {
            return $path;
        }

        $pos = strpos($path, '/');
        $alias = $pos === false ? $path : substr($path, 1, $pos-1);

        if (isset(static::$paths[$alias])) {
            return $pos === false ? static::$paths[$alias] : static::$paths[$alias] . substr($path, $pos);
        }
        return $path;
    }


    public static function error($msg, $category = 'app') {

        static::log(Logger::ERROR, $msg, $category);
    }

    public static function warning($msg, $category = 'app') {
        static::log(Logger::WARNING, $msg, $category);
    }

    public static function info($msg, $category = 'app') {
        static::log(Logger::INFO, $msg, $category);
    }

    public static function notice($msg, $category = 'app') {
        static::log(Logger::NOTICE, $msg, $category);
    }

    public static function log($level, $msg, $category) {
        if(static::$app->has('log'))
            static::$app->get('log')->log($level, $msg, $category);
    }

    public static function message(string $file, string $path = null, $default = null)
    {
        static $messages;
        $file = Mii::resolve($file);

        if ( ! isset($messages[$file]))
        {

            if(file_exists($file.'.php')) {
                $content = include($file.'.php');
            } elseif(file_exists(path('app').'/messages/'.$file.'.php')) {
                $content = include(path('app').'/messages/'.$file.'.php');
            } else {
                throw new \Exception("Message file does not exist: $file.php");
            }
            // Create a new message list
            $messages[$file] = [];
            $messages[$file] = $content;

        }
        if ($path === null)
        {
            // Return all of the messages
            return $messages[$file];
        }
        else
        {
            // Get a message using the path
            return \mii\util\Arr::path($messages[$file], $path, $default);
        }
    }


    public static function t($category, $message, $params = [], $language = null)
    {
        if (static::$app !== null) {
            return static::$app->I18n->translate($category, $message, $params, $language ?: static::$app->language);
        } else {
            $p = [];
            foreach ((array) $params as $name => $value) {
                $p['{' . $name . '}'] = $value;
            }
            return ($p === []) ? $message : strtr($message, $p);
        }
    }
}


function url(string $name, array $params = []) : string {
    return Mii::$app->router->url($name, $params);
}


function redirect($url, $use_back_url = false) {

    if($use_back_url) {
        $url = \mii\util\URL::back_url($url);
    }

    throw new \mii\web\RedirectHttpException($url);
}

/**
 * @param $name string
 * @return \mii\web\Block
 */
function block(string $name) : \mii\web\Block {
    return Mii::$app->blocks->get($name);
}

/**
 * Retrieve a cached value entry by id.
 *
 *     // Retrieve cache entry with id foo
 *     $data = get_cached('foo');
 *
 *     // Retrieve cache entry and return 'bar' if miss
 *     $data = get_cached('foo', 'bar');
 *
 * @param   string $id id of cache to entry
 * @param   string $default default value to return if cache miss
 * @return  mixed
 * @throws  \mii\cache\CacheException
 */
function get_cached($id, $default = null, $lifetime = null) {


    if(is_object($default) && $default instanceof \Closure) {

        $cached = Mii::$app->cache->get($id);
        if($cached === null) {
            $cached = call_user_func($default);

            Mii::$app->cache->set($id, $cached, $lifetime);
        }
        return $cached;
    }

    return Mii::$app->cache->get($id, $default);

}

/**
 * Set a value to cache with id and lifetime
 *
 *     $data = 'bar';
 *
 *     // Set 'bar' to 'foo', using default expiry
 *     cache('foo', $data);
 *
 *     // Set 'bar' to 'foo' for 30 seconds
 *     cache('foo', $data, 30);
 *
 * @param   string $id id of cache entry
 * @param   string $data data to set to cache
 * @param   integer $lifetime lifetime in seconds
 * @return  boolean
 */

function cache($id, $data, $lifetime = null) {
    return Mii::$app->cache->set($id, $data, $lifetime);
}



/**
 * Delete a cache entry based on id, or delete all cache entries.
 *
 *     // Delete 'foo' entry from the apc group
 *     Cache::instance('apc')->delete('foo');
 *
 * @param   string $id id to remove from cache
 * @return  boolean
 */

function clear_cache($id = null) {
    if($id === null)
        return Mii::$app->cache->delete_all();

    return Mii::$app->cache->delete($id);
}


function path(string $name) : string {
    return Mii::$paths[$name];
}

function e(?string $text) : string {
    return mii\util\HTML::entities($text, false);
}

if( ! function_exists('dd')) {
    /**
     * Dump and die
     */
    function dd(...$params)
    {
        if(Mii::$app instanceof \mii\web\App) {

            echo "<style>pre { padding: 5px; background-color: #f9feff; font-size: 14px; font-family: monospace; text-align: left; color: #111;overflow: auto; white-space: pre-wrap; }";
            echo "pre small { font-size: 1em; color: #000080;font-weight:bold}";
            echo "</style><pre>\n";

            array_map(function($a) {
                echo \mii\util\Debug::dump($a);
            }, $params);

            echo "</pre>\n";
        } else {

            array_map(function($a) {
                var_dump($a);
            }, $params);
        }
        die;
    }
}

if ( ! function_exists('__')) {
    function __($string, $params = []) {
        $string = Mii::$app->i18n->translate($string);
        return empty($values) ? $string : strtr($string, $values);
    }
}


function config(string $key, $default = null) {
    if(isset( Mii::$app->_config[$key]) || array_key_exists($key, Mii::$app->_config))
        return Mii::$app->_config[$key];

    if(strpos($key, '.') !== false)
        return \mii\util\Arr::path(Mii::$app->_config, $key, $default);

    return $default;
}

function config_set(string $key, $value) {
    \mii\util\Arr::set_path(Mii::$app->_config, $key, $value, '.');
}
