<?php

use mii\Log\Logger;

defined('MIIPATH') or define('MIIPATH', __DIR__);

defined('MII_ENV') or define('MII_ENV', 'dev');

defined('MII_ENV_DEV') or define('MII_ENV_DEV', MII_ENV === 'dev');

/**
 * profiling
 */
defined('MII_PROF') or define('MII_PROF', MII_ENV === 'dev');

defined('MII_START_TIME') or  define('MII_START_TIME', microtime(TRUE));
defined('MII_START_MEMORY') or define('MII_START_MEMORY', memory_get_usage());



class Mii {

    const VERSION = '1.0.0';

    const CODENAME = 'Alnair';

    /**
     * @var \mii\web\App|\mii\console\App;
     */
    public static $app = null;

    /**
    * @var Array of Logger's
    */
    public static $_loggers;


    public static function autoloader($class) {

        // what prefixes should be recognized?
        $prefixes = [
            "app\\" => [
                __DIR__ . '/../app/',
            ],
            "mii\\" => [
                __DIR__ . '/../../mii/src',
            ],
        ];

        // go through the prefixes
        foreach ($prefixes as $prefix => $dirs) {


            // does the requested class match the namespace prefix?
            $prefix_len = strlen($prefix);
            if (substr($class, 0, $prefix_len) !== $prefix) {
                continue;
            }

            // strip the prefix off the class
            $class = substr($class, $prefix_len);

            // a partial filename
            $part = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

            // go through the directories to find classes
            foreach ($dirs as $dir) {
                $dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);
                $file = $dir . DIRECTORY_SEPARATOR . $part;
                //if (is_readable($file)) {
                require $file;
                return;
                //}
            }
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
        foreach(static::$_loggers as $logger) {
            $logger->log($level, $msg, $category);
        }
    }

    public static function flush_logs() {
        foreach(static::$_loggers as $logger) {
            $logger->flush();
        }
    }

    public static function t($category, $message, $params = [], $language = null)
    {
        if (static::$app !== null) {
            return static::$app->getI18n()->translate($category, $message, $params, $language ?: static::$app->language);
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

function block($name) {
    return Mii::$app->blocks()->get($name);
}


function config($group = false, $value = false) {
    if($value) {
        if($group)
            Mii::$app->_config[$group] = $value;
        else
            Mii::$app->_config = $value;

    } else {

        if ($group) {
            if(isset(Mii::$app->_config[$group]))
                return Mii::$app->_config[$group];
            return [];
        }

        return Mii::$app->_config;
    }
}
