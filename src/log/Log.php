<?php declare(strict_types=1);

namespace mii\log;

class Log
{
    public static function error(...$args): void
    {
        static::log(Logger::ERROR, $args);
    }

    public static function warning(...$args): void
    {
        static::log(Logger::WARNING, $args);
    }

    public static function info(...$args): void
    {
        static::log(Logger::INFO, $args);
    }


    private static function log(int $level, array $args): void
    {
        if (count($args) === 1 && $args[0] instanceof \Throwable) {
            \Mii::log($level, $args[0], 'app');
            return;
        }

        $message = [];
        $exceptions = [];

        $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $message[] = ($trace[2]['class'] ? $trace[2]['class'] . "->" : '') . $trace[2]['function'];

        foreach ($args as $arg) {
            if (\is_string($arg) || \is_int($arg)) {
                $message[] = $arg;
                continue;
            }

            if (\is_object($arg)) {
                $classname = \get_class($arg);

                if ($arg instanceof \Throwable) {
                    $exceptions[] = $arg;
                }

                if (($arg instanceof \mii\db\ORM) && isset($arg->id)) {
                    $classname .= "({$arg->id})";
                }
                $message[] = $classname;
            } else {
                $message[] = \var_export($arg, true);
            }
        }

        \Mii::log($level, \implode(' ', $message), 'app');

        foreach ($exceptions as $e) {
            \Mii::log($level, $e, 'app');
        }
    }
}
