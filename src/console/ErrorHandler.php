<?php declare(strict_types=1);

namespace mii\console;

use mii\core\ErrorException;
use mii\util\Console;

class ErrorHandler extends \mii\core\ErrorHandler
{
    public int $memory_reserve_size = 262144;

    public function render($exception): void
    {
        if (config('debug')) {
            if ($exception instanceof ErrorException) {
                $message = $this->formatMessage($exception->getName());
            } else {
                $message = $this->formatMessage('Exception');
            }

            $message .= $this->formatMessage(" '" . \get_class($exception) . "'", [Console::BOLD, Console::FG_BLUE])
                . ' with message ' . $this->formatMessage("'{$exception->getMessage()}'", [Console::BOLD]) //. "\n"
                . "\n\nin " . \dirname($exception->getFile()) . \DIRECTORY_SEPARATOR . $this->formatMessage(\basename($exception->getFile()), [Console::BOLD])
                . ':' . $this->formatMessage($exception->getLine(), [Console::BOLD, Console::FG_YELLOW]) . "\n"
                . "\n" . $this->formatMessage("Stack trace:\n", [Console::BOLD]) . $exception->getTraceAsString();
        } else {
            $message = $this->formatMessage('Error: ') . $exception->getMessage();
        }

        if (\PHP_SAPI === 'cli') {
            Console::stderr($message . "\n");
        } else {
            echo $message . "\n";
        }
    }

    /**
     * Colorizes a message for console output.
     * @param string $message the message to colorize.
     * @param array $format the message format.
     * @return string the colorized message.
     * @see Console::ansiFormat() for details on how to specify the message format.
     */
    protected function formatMessage($message, array $format = [Console::FG_RED, Console::BOLD]): string
    {
        $stream = (\PHP_SAPI === 'cli') ? \STDERR : \STDOUT;

        if (Console::streamSupportsAnsiColors($stream)) {
            $message = Console::ansiFormat((string) $message, $format);
        }

        return $message;
    }
}
