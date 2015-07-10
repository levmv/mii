<?php

namespace mii\core;


abstract class Exception extends \Exception {

    /**
     * @var  array  PHP error code => human readable name
     */
    public static $php_errors = [
        E_ERROR              => 'Fatal Error',
        E_USER_ERROR         => 'User Error',
        E_PARSE              => 'Parse Error',
        E_WARNING            => 'Warning',
        E_USER_WARNING       => 'User Warning',
        E_STRICT             => 'Strict',
        E_NOTICE             => 'Notice',
        E_RECOVERABLE_ERROR  => 'Recoverable Error',
        E_DEPRECATED         => 'Deprecated',
    ];


    /**
     * @var  Request    Request instance that triggered this exception.
     */
    protected $_request;


    /**
     * Creates a new translated exception.
     *
     *     throw new Exception('Something went terrible wrong, :user',
     *         array(':user' => $user));
     *
     * @param   string          $message    error message
     * @param   array           $variables  translation variables
     * @param   integer|string  $code       the exception code
     * @param   Exception       $previous   Previous exception
     * @return  void
     */
    public function __construct($message = "", array $variables = NULL, $code = 0, Exception $previous = NULL)
    {

        // Set the message
        $message = empty($variables) ? $message : strtr($message, $variables);

        // Pass the message and integer code to the parent
        parent::__construct($message, (int) $code, $previous);

        // Save the unmodified code
        // @link http://bugs.php.net/39615
        $this->code = $code;
    }

    /**
     * Magic object-to-string method.
     *
     *     echo $exception;
     *
     * @uses    Exception::text
     * @return  string
     */
    public function __toString()
    {
        return Exception::text($this);
    }


    /**
     * Removes all output echoed before calling this method.
     */
    public function clear_output()
    {
        // the following manual level counting is to deal with zlib.output_compression set to On
        for ($level = ob_get_level(); $level > 0; --$level) {
            if (!@ob_end_clean()) {
                ob_clean();
            }
        }
    }

    /**
     * Converts an exception into a PHP error.
     *
     * This method can be used to convert exceptions inside of methods like `__toString()`
     * to PHP errors because exceptions cannot be thrown inside of them.
     * @param \Exception $exception the exception to convert to a PHP error.
     */
    public static function convert_to_error($exception)
    {
        trigger_error(static::text($exception), E_USER_ERROR);
    }



    /**
     * Get a single line of text representing the exception:
     *
     * Error [ Code ]: Message ~ File [ Line ]
     *
     * @param   \Exception  $e
     * @return  string
     */
    public static function text(\Exception $e)
    {
        return sprintf('%s [ %s ]: %s ~ %s [ %d ]',
            get_class($e), $e->getCode(), strip_tags($e->getMessage()), \mii\util\Debug::path($e->getFile()), $e->getLine());
    }



} // End Kohana_Exception
