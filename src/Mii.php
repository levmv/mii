<?php

use mii\Log\Logger;

defined('MII_START_TIME') or define('MII_START_TIME', microtime(true));
defined('MII_START_MEMORY') or define('MII_START_MEMORY', memory_get_usage());



class Mii {

    const VERSION = '0.9.1';

    const CODENAME = 'Alnair';

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

    /**
    * @var Array of Logger's
    */
    public static $_loggers = [];

    public static function autoloader($class) {


        // go through the prefixes
        foreach (static::$paths as $prefix => $path) {

            if (strpos($class, $prefix) !== 0) {
                continue;
            }

            // strip the prefix off the class
            $class = substr($class, strlen($prefix)+1);


            // a partial filename
            $part = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';


            if (is_file($path . DIRECTORY_SEPARATOR . $part)) {
                require $path . DIRECTORY_SEPARATOR . $part;
                return;
            }
        }
    }


    public static function get_path($name) {
        return static::$paths[$name];
    }


    public static function set_path($name, $value = null) {
        if(is_array($name)) {
            static::$paths = $name;
        } else {
            static::$paths[$name] = $value;
        }
    }


    /**
     * Sets the logger object.
     * @param Logger $logger the logger object.
     */
    public static function add_logger(Logger $logger) {
        static::$_loggers[] = $logger;
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
        static::$app->get('log')->log($level, $msg, $category);
    }

    public static function flush_logs() {
        foreach(static::$_loggers as $logger) {
            $logger->flush();
        }
    }

    public static function message($file, $path = NULL, $default = NULL)
    {
        static $messages;
        if ( ! isset($messages[$file]))
        {
            // Create a new message list
            $messages[$file] = array();
            $messages[$file] = include(path('app').'/messages/'.$file.'.php');

        }
        if ($path === NULL)
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


function redirect($url) {
    throw new \mii\web\RedirectHttpException($url);
}

/**
 * @param $name
 * @return \mii\web\Block
 */
function block($name) {
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
function get_cached($name, $default = null) {
    return Mii::$app->cache->get($name, $default);
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


function path($name) {
    return Mii::$paths[$name];
}

function e($text) {
    return mii\util\Html::entities($text, false);
}


function config($group = null, $value = null) {
    if($value !== null) {
        if($group !== null)
            Mii::$app->_config[$group] = $value;
        else
            Mii::$app->_config = $value;

    } else {

        if ($group !== null) {
            if(isset(Mii::$app->_config[$group]))
                return Mii::$app->_config[$group];
            return [];
        }

        return Mii::$app->_config;
    }
}
