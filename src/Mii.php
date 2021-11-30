<?php /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpIncludeInspection */
/** @noinspection PhpIllegalPsrClassPathInspection */
declare(strict_types=1);

use mii\log\Logger;

\assert(\defined('MII_START_TIME') or \define('MII_START_TIME', \hrtime(true) / 1e9));
\assert(\defined('MII_START_MEMORY') or \define('MII_START_MEMORY', \memory_get_usage()));

class Mii
{
    /**
     * @var \mii\web\App|\mii\console\App $app
     */
    public static $app = null;

    public static string $log_component_name = 'log';

    public static array $paths = [];

    public static function get_path(string $name): string
    {
        return static::$paths[$name];
    }


    public static function set_path($name, $value = null): void
    {
        if (\is_array($name)) {
            if (empty(static::$paths)) {
                static::$paths = $name;
            } else {
                static::$paths = \array_replace(static::$paths, $name);
            }
        } else {
            static::$paths[$name] = $value;
        }
    }


    public static function resolve(string $path): string
    {
        if (empty($path) || $path[0] !== '@') {
            return $path;
        }

        $pos = \strpos($path, '/');
        $alias = $pos === false ? \substr($path, 1) : \substr($path, 1, $pos - 1);

        if (isset(static::$paths[$alias])) {
            return $pos === false ? static::$paths[$alias] : static::$paths[$alias] . \substr($path, $pos);
        }
        return $path;
    }


    public static function error($msg, $category = 'app') : void
    {
        static::log(Logger::ERROR, $msg, $category);
    }

    public static function warning($msg, $category = 'app') : void
    {
        static::log(Logger::WARNING, $msg, $category);
    }

    public static function info($msg, $category = 'app') : void
    {
        static::log(Logger::INFO, $msg, $category);
    }

    public static function log($level, $msg, $category)
    {
        if (static::$app->has(static::$log_component_name)) {
            static::$app->get(static::$log_component_name)->log($level, $msg, $category);
        }
    }

    public static function message(string $file, string $path = null, $default = null)
    {
        static $messages;
        $file = self::resolve($file);

        if (!isset($messages[$file])) {
            if (\file_exists($file . '.php')) {
                $content = include $file . '.php';
            } elseif (isset(self::$paths['app']) && \file_exists(\path('app') . '/messages/' . $file . '.php')) {
                $content = include \path('app') . '/messages/' . $file . '.php';
            } else {
                throw new \Exception("Message file does not exist: $file.php");
            }
            $messages[$file] = $content;
        }
        if ($path === null) {
            // Return all of the messages
            return $messages[$file];
        }

        // Get a message using the path
        return \mii\util\Arr::path($messages[$file], $path, $default);
    }
}
