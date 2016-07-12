<?php

namespace mii\core;


abstract class Exception extends \Exception {

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


    public function get_name() {
        return 'Exception';
    }


    /**
     * Get a single line of text representing the exception:
     *
     * Error [ Code ]: Message ~ File [ Line ]
     *
     * @param   \Throwable  $e
     * @return  string
     */
    public static function text(\Throwable $e)
    {
        return sprintf('%s [ %s ]: %s ~ %s [ %d ]',
            get_class($e), $e->getCode(), strip_tags($e->getMessage()), \mii\util\Debug::path($e->getFile()), $e->getLine());
    }





} // End Kohana_Exception
