<?php

use mii\Log\Logger;

defined('MII_PATH') or define('MII_PATH', __DIR__);

defined('MII_DEBUG') or define('MII_DEBUG', true);

defined('MII_PROF') or define('MII_PROF', MII_DEBUG === true);

if(MII_PROF) {
    defined('MII_START_TIME') or define('MII_START_TIME', microtime(true));
    defined('MII_START_MEMORY') or define('MII_START_MEMORY', memory_get_usage());
}


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

    public static $autoload_prefixes = [
        "app\\" => APP_PATH,
        "mii\\" => MII_PATH
    ];

    public static function autoloader($class) {


        // go through the prefixes
        foreach (static::$autoload_prefixes as $prefix => $path) {

            if (strpos($class, $prefix) !== 0) {
                continue;
            }

            // strip the prefix off the class
            $class = substr($class, strlen($prefix));

            // a partial filename
            $part = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';


            if (is_file($path . DIRECTORY_SEPARATOR . $part)) {
                require $path . DIRECTORY_SEPARATOR . $part;
                return;
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

/**
 * @param $name
 * @return \mii\web\Block
 */
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


