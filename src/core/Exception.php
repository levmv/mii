<?php declare(strict_types=1);

namespace mii\core;

use ReturnTypeWillChange;

class Exception extends \Exception
{

    /**
     * Magic object-to-string method.
     *
     *     echo $exception;
     *
     * @return  string
     * @uses    Exception::text
     */
    #[ReturnTypeWillChange] public function __toString()
    {
        return static::text($this);
    }


    /**
     * Get a single line of text representing the exception:
     *
     * Error [Code]: Message ~ File [ Line ]
     *
     * @param \Throwable $e
     * @return  string
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public static function text(\Throwable $e): string
    {
        $code = $e->getCode();

        return \sprintf(
            '%s%s: %s ~ %s[%d]',
            (new \ReflectionClass($e))->getShortName(),
            $code !== 0 ? "[$code]" : '',
            \strip_tags($e->getMessage()),
            \mii\util\Debug::path($e->getFile()),
            $e->getLine()
        );
    }
}
