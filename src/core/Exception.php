<?php

namespace mii\core;


class Exception extends \Exception
{

    /**
     * Magic object-to-string method.
     *
     *     echo $exception;
     *
     * @uses    Exception::text
     * @return  string
     */
    public function __toString() {
        return static::text($this);
    }


    /**
     * Get a single line of text representing the exception:
     *
     * Error [Code]: Message ~ File [ Line ]
     *
     * @param   \Throwable $e
     * @return  string
     */
    public static function text(\Throwable $e) {
        $code = $e->getCode();
        return sprintf('%s[%s]: %s ~ %s[%d]',
            (new \ReflectionClass($e))->getShortName(), $code != 0 ? "[$code]" : '', strip_tags($e->getMessage()), \mii\util\Debug::path($e->getFile()), $e->getLine());
    }


}
