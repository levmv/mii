<?php declare(strict_types=1);

namespace mii\core;

class ErrorHandler extends Component
{
    public ?\Throwable $exception = null;

    /**
     * @var integer the size of the reserved memory. A portion of memory is pre-allocated so that
     * when an out-of-memory issue occurs, the error handler is able to handle the error with
     * the help of this reserved memory. If you set this value to be 0, no memory will be reserved.
     * Defaults to 0;
     */
    public int $memory_reserve_size = 0;

    /**
     * @var string Used to reserve memory for fatal error handler.
     */
    private string $_memory_reserve;

    public function register()
    {
        \ini_set('display_errors', '0');
        \set_exception_handler([$this, 'handleException']);
        \set_error_handler([$this, 'handleError']);

        if ($this->memory_reserve_size > 0) {
            $this->_memory_reserve = \str_repeat('x', $this->memory_reserve_size);
        }
        \register_shutdown_function([$this, 'handleFatalError']);
    }

    public function unregister()
    {
        \restore_error_handler();
        \restore_exception_handler();
    }


    public function report($exception)
    {
        \Mii::error($exception, \get_class($exception));
    }


    public function render($exception)
    {
    }


    public function handleException($e)
    {
        // disable error capturing to avoid recursive errors while handling exceptions
        $this->unregister();

        try {
            $this->exception = $e = $this->prepareException($e);
            $this->report($e);
            $this->clearOutput();
            $this->render($e);
        } catch (\Throwable $e) {
            \http_response_code(500);
            echo static::exceptionToText($e);
        }
        exit(1);
    }

    public function prepareException(\Throwable $e): \Throwable
    {
        return $e;
    }


    public function handleError($code, $error, $file, $line)
    {
        if (\error_reporting() & $code) {
            unset($this->_memory_reserve);

            // This error is not suppressed by current error reporting settings
            // Convert the error into an ErrorException
            throw new ErrorException($error, $code, 0, $file, $line);
        }
        // Do not execute the PHP error handler (?)
        return true;
    }


    public function handleFatalError()
    {
        unset($this->_memory_reserve);

        // is it fatal ?
        if ($error = \error_get_last() && \in_array($error['type'], [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_CORE_WARNING, \E_COMPILE_ERROR, \E_COMPILE_WARNING])) {
            $this->clearOutput();
            $exception = new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']);

            $this->report($exception);
            $this->render($exception);

            if (\Mii::$app->has(\Mii::$log_component_name)) {
                \Mii::$app->get(\Mii::$log_component_name)->flush();
            }

            // Shutdown now to avoid a "death loop"
            exit(1);
        }
        return false;
    }


    /**
     * Removes all output echoed before calling this method.
     */
    public function clearOutput()
    {
        // the following manual level counting is to deal with zlib.output_compression set to On
        for ($level = \ob_get_level(); $level > 0; --$level) {
            if (!@\ob_end_clean()) {
                \ob_clean();
            }
        }
    }


    public static function exceptionToText($e)
    {
        if (\config('debug')) {
            return (string) $e;
        }

        if ($e instanceof UserException) {
            return \get_class($e) . ' : ' . $e->getMessage();
        }

        return 'An internal server error occurred.';
    }
}
