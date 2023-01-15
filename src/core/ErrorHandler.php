<?php /** @noinspection PhpPropertyOnlyWrittenInspection */
declare(strict_types=1);

namespace mii\core;

use mii\util\Debug;

class ErrorHandler extends Component
{
    public ?\Throwable $exception = null;

    /**
     * @var integer the size of the reserved memory. A portion of memory is pre-allocated so that
     * when an out-of-memory issue occurs, the error handler is able to handle the error with
     * the help of this reserved memory. By default, no memory will be reserved.
     */
    public int $memoryReserveSize = 0;

    /**
     * @var string Used to reserve memory for fatal error handler.
     */
    private string $memoryReserve;

    public function register(): void
    {
        \ini_set('display_errors', '0');
        \set_exception_handler($this->handleException(...));
        \set_error_handler($this->handleError(...));

        if ($this->memoryReserveSize > 0) {
            $this->memoryReserve = \str_repeat('x', $this->memoryReserveSize);
        }
        \register_shutdown_function([$this, 'handleFatalError']);
    }

    public function unregister(): void
    {
        \restore_error_handler();
        \restore_exception_handler();
    }


    public function report($exception): void
    {
        \Mii::error($exception, $exception::class);
    }


    public function render($exception): void
    {
    }


    public function handleException($e): void
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


    public function handleError($code, $error, $file, $line): bool
    {
        if (\error_reporting() & $code) {
            unset($this->memoryReserve);

            // This error is not suppressed by current error reporting settings
            // Convert the error into an ErrorException
            throw new ErrorException($error, $code, 0, $file, $line);
        }
        // Do not execute the PHP error handler (?)
        return true;
    }


    public function handleFatalError()
    {
        unset($this->memoryReserve);

        // is it fatal ?
        if (null !== ($error = \error_get_last()) && \in_array($error['type'], [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_CORE_WARNING, \E_COMPILE_ERROR, \E_COMPILE_WARNING], true)) {
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
    public function clearOutput(): void
    {
        // the following manual level counting is to deal with zlib.output_compression set to On
        for ($level = \ob_get_level(); $level > 0; --$level) {
            if (!@\ob_end_clean()) {
                \ob_clean();
            }
        }
    }


    public static function exceptionToText(\Throwable $e): string
    {
        if (\config('debug')) {
            return Debug::exceptionToText($e);
        }

        if ($e instanceof UserException) {
            return $e::class . ' : ' . $e->getMessage();
        }

        return 'An internal server error occurred.';
    }
}
