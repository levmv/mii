<?php declare(strict_types=1);

namespace mii\core;

class ErrorException extends \ErrorException
{
    /**
     * Constructs the exception.
     * @link http://php.net/manual/en/errorexception.construct.php
     * @param $message [optional]
     * @param $code [optional]
     * @param $severity [optional]
     * @param $filename [optional]
     * @param $lineno [optional]
     * @param $previous [optional]
     */
    public function __construct($message = '', $code = 0, $severity = 1, $filename = __FILE__, $lineno = __LINE__, \Exception $previous = null)
    {
        parent::__construct($message, $code, $severity, $filename, $lineno, $previous);

        if (\function_exists('xdebug_get_function_stack')) {
            $trace = \array_slice(\array_reverse(xdebug_get_function_stack()), 3, -1);
            foreach ($trace as &$frame) {
                if (!isset($frame['function'])) {
                    $frame['function'] = 'unknown';
                }

                if ($frame['type'] === 'static') {
                    $frame['type'] = '::';
                } elseif ($frame['type'] === 'dynamic') {
                    $frame['type'] = '->';
                }

                // XDebug has a different key name
                if (isset($frame['params']) && !isset($frame['args'])) {
                    $frame['args'] = $frame['params'];
                }
            }


            $ref = new \ReflectionProperty('Exception', 'trace');
            $ref->setAccessible(true);
            $ref->setValue($this, $trace);
        }
    }

    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        static $names = [
            \E_COMPILE_ERROR => 'PHP Compile Error',
            \E_COMPILE_WARNING => 'PHP Compile Warning',
            \E_CORE_ERROR => 'PHP Core Error',
            \E_CORE_WARNING => 'PHP Core Warning',
            \E_DEPRECATED => 'PHP Deprecated Warning',
            \E_ERROR => 'PHP Fatal Error',
            \E_NOTICE => 'PHP Notice',
            \E_PARSE => 'PHP Parse Error',
            \E_RECOVERABLE_ERROR => 'PHP Recoverable Error',
            \E_STRICT => 'PHP Strict Warning',
            \E_USER_DEPRECATED => 'PHP User Deprecated Warning',
            \E_USER_ERROR => 'PHP User Error',
            \E_USER_NOTICE => 'PHP User Notice',
            \E_USER_WARNING => 'PHP User Warning',
            \E_WARNING => 'PHP Warning',
        ];

        return $names[$this->getCode()] ?? 'Error';
    }
}
