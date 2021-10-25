<?php declare(strict_types=1);

/**
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @author Carsten Brandt <mail@cebe.cc>
 */

namespace mii\util;

class Console
{
    public const FG_BLACK = 30;
    public const FG_RED = 31;
    public const FG_GREEN = 32;
    public const FG_YELLOW = 33;
    public const FG_BLUE = 34;
    public const FG_PURPLE = 35;
    public const FG_CYAN = 36;
    public const FG_GREY = 37;

    public const BG_BLACK = 40;
    public const BG_RED = 41;
    public const BG_GREEN = 42;
    public const BG_YELLOW = 43;
    public const BG_BLUE = 44;
    public const BG_PURPLE = 45;
    public const BG_CYAN = 46;
    public const BG_GREY = 47;

    public const RESET = 0;
    public const NORMAL = 0;
    public const BOLD = 1;
    public const ITALIC = 3;
    public const UNDERLINE = 4;
    public const BLINK = 5;
    public const NEGATIVE = 7;
    public const CONCEALED = 8;
    public const CROSSED_OUT = 9;
    public const FRAMED = 51;
    public const ENCIRCLED = 52;
    public const OVERLINED = 53;


    /**
     * Will return a string formatted with the given ANSI style
     *
     * @param string $string the string to be formatted
     * @param array  $format An array containing formatting values.
     * You can pass any of the FG_*, BG_* and TEXT_* constants
     * and also [[xtermFgColor]] and [[xtermBgColor]] to specify a format.
     * @return string
     */
    public static function ansiFormat(string $string, array $format = []): string
    {
        $code = \implode(';', $format);

        return "\033[0m" . ($code !== '' ? "\033[" . $code . 'm' : '') . $string . "\033[0m";
    }

    /**
     * Strips ANSI control codes from a string
     *
     * @param string $string String to strip
     * @return string
     */
    public static function stripAnsiFormat(string $string): string
    {
        return \preg_replace('/\033\[[\d;?]*\w/', '', $string);
    }


    /**
     * Escapes % so they don't get interpreted as color codes when
     * the string is parsed by [[renderColoredString]]
     *
     * @param string $string String to escape
     *
     * @access public
     * @return string
     */
    public static function escape(string $string): string
    {
        return \str_replace('%', '%%', $string);
    }

    /**
     * Returns true if the stream supports colorization. ANSI colors are disabled if not supported by the stream.
     *
     * - windows without ansicon
     * - not tty consoles
     *
     * @param mixed $stream
     * @return boolean true if the stream supports ANSI colors, otherwise false.
     */
    public static function streamSupportsAnsiColors($stream)
    {
        return \DIRECTORY_SEPARATOR === '\\'
            ? \getenv('ANSICON') !== false || \getenv('ConEmuANSI') === 'ON'
            : \function_exists('posix_isatty') && @\posix_isatty($stream);
    }


    /**
     * Gets input from STDIN and returns a string right-trimmed for EOLs.
     *
     * @param boolean $raw If set to true, returns the raw string without trimming
     * @return string the string read from stdin
     */
    public static function stdin(bool $raw = false): string
    {
        return $raw ? \fgets(\STDIN) : \rtrim(\fgets(\STDIN), \PHP_EOL);
    }

    /**
     * Prints a string to STDOUT.
     *
     * @param string $string the string to print
     * @param array  $args
     * @return int|boolean Number of bytes printed or false on error
     */
    public static function stdout(mixed $string, ...$args)
    {
        return static::writeToStream(\STDOUT, (string) $string, ...$args);
    }

    /**
     * Prints a string to STDERR.
     *
     * @param string $string the string to print
     * @return int|boolean Number of bytes printed or false on error
     */
    public static function stderr(mixed $string, ...$args)
    {
        return static::writeToStream(\STDERR, (string) $string, ...$args);
    }

    public static function writeToStream($stream, $string, ...$args)
    {
        if (!empty($args) && static::streamSupportsAnsiColors($stream)) {
            $string = static::ansiFormat($string, $args);
        }
        return \fwrite($stream, $string);
    }


    /**
     * Asks the user for input. Ends when the user types a carriage return (PHP_EOL). Optionally, It also provides a
     * prompt.
     *
     * @param string|null $prompt the prompt to display before waiting for input (optional)
     * @return string the user's input
     */
    public static function input(string $prompt = null): string
    {
        if (isset($prompt)) {
            static::stdout($prompt);
        }

        return static::stdin();
    }

    /**
     * Prints text to STDOUT appended with a carriage return (PHP_EOL).
     *
     * @param string $string the text to print
     * @return integer|boolean number of bytes printed or false on error.
     */
    public static function output($string = null)
    {
        return static::stdout($string . \PHP_EOL);
    }

    /**
     * Prints text to STDERR appended with a carriage return (PHP_EOL).
     *
     * @param string|null $string the text to print
     * @return integer|boolean number of bytes printed or false on error.
     */
    public static function error(string $string = null)
    {
        return static::stderr($string . \PHP_EOL);
    }

    /**
     * Asks user to confirm by typing y or n.
     *
     * @param string $message to print out before waiting for user input
     * @param boolean $default this value is returned if no selection is made.
     * @return bool|null whether user confirmed
     */
    public static function confirm(string $message, $default = false): ?bool
    {
        while (true) {
            static::stdout($message . ' (yes|no) [' . ($default ? 'yes' : 'no') . ']:');
            $input = \trim(static::stdin());

            if (empty($input)) {
                return $default;
            }

            if (!\strcasecmp($input, 'y') || !\strcasecmp($input, 'yes')) {
                return true;
            }

            if (!\strcasecmp($input, 'n') || !\strcasecmp($input, 'no')) {
                return false;
            }
        }
    }

    /**
     * Gives the user an option to choose from. Giving '?' as an input will show
     * a list of options to choose from and their explanations.
     *
     * @param string $prompt the prompt message
     * @param array $options Key-value array of options to choose from
     *
     * @return string An option character the user chose
     */
    public static function select(string $prompt, array $options = []): string
    {
        top:
        static::stdout("$prompt [" . \implode(',', \array_keys($options)) . ',?]: ');
        $input = static::stdin();
        if ($input === '?') {
            foreach ($options as $key => $value) {
                static::output(" $key - $value");
            }
            static::output(' ? - Show help');
            goto top;
        } elseif (!\array_key_exists($input, $options)) {
            goto top;
        }

        return $input;
    }
}
