<?php

namespace mii\core;


class ErrorHandler {

    /**
     * @var integer the size of the reserved memory. A portion of memory is pre-allocated so that
     * when an out-of-memory issue occurs, the error handler is able to handle the error with
     * the help of this reserved memory. If you set this value to be 0, no memory will be reserved.
     * Defaults to 0;
     * Copyright (c) 2008 Yii Software LLC
     */
    public $memory_reserve_size = 0;

    /**
     * @var string Used to reserve memory for fatal error handler.
     */
    private $_memory_reserve;


    public function __construct($config = []) {
        foreach($config as $key => $value)
            $this->$key = $value;
    }


    public function register() {
        ini_set('display_errors', 'off');
        set_exception_handler([$this, 'handle_exception']);
        set_error_handler([$this, 'handle_error']);

        if ($this->memory_reserve_size > 0) {
            $this->_memory_reserve = str_repeat('x', $this->memory_reserve_size);
        }
        register_shutdown_function([$this, 'handle_fatal_error']);
    }

    public function unregister()
    {
        restore_error_handler();
        restore_exception_handler();
    }


    public function report($exception) {

        \Mii::error(Exception::text($exception), 'mii');
    }


    public function render($exception) {

    }



    public function handle_exception($e)
    {
        // disable error capturing to avoid recursive errors while handling exceptions
        $this->unregister();


        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
        }

        try {

            $this->report($e);

            $this->clear_output();

            $this->render($e);

            exit(1);

        } catch (\Exception $e) {
            echo Exception::text($e);
            exit(1);
        }

    }


    public function handle_error($code, $error, $file, $line)
    {
        if (error_reporting() & $code) {

            unset($this->_memory_reserve);

            // This error is not suppressed by current error reporting settings
            // Convert the error into an ErrorException
            $exception = new ErrorException($error, $code, 0, $file, $line);

            // in case error appeared in __toString method we can't throw any exception
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            array_shift($trace);
            foreach ($trace as $frame) {
                if ($frame['function'] === '__toString') {
                    $this->handle_exception($exception);
                    exit(1);
                }
            }

            throw $exception;
        }
        // Do not execute the PHP error handler (?)
        return true;
    }


    public function handle_fatal_error() {

        unset($this->_memory_reserve);


        // is it fatal ?
        if ($error = error_get_last() AND in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING]))
        {
            $this->clear_output();
            $exception = new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']);

            $this->report($exception);
            $this->render($exception);
            
            \Mii::$app->log && \Mii::$app->log->flush();

            // Shutdown now to avoid a "death loop"
            exit(1);
        }
        return false;
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
        trigger_error(Exception::text($exception), E_USER_ERROR);
    }

}