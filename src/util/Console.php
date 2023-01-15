<?php declare(strict_types=1);

/**
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @author Carsten Brandt <mail@cebe.cc>
 */

namespace mii\util;

class Console
{
    final public const FG_BLACK = 30;
    final public const FG_RED = 31;
    final public const FG_GREEN = 32;
    final public const FG_YELLOW = 33;
    final public const FG_BLUE = 34;
    final public const FG_PURPLE = 35;
    final public const FG_CYAN = 36;
    final public const FG_GREY = 37;

    final public const BG_BLACK = 40;
    final public const BG_RED = 41;
    final public const BG_GREEN = 42;
    final public const BG_YELLOW = 43;
    final public const BG_BLUE = 44;
    final public const BG_PURPLE = 45;
    final public const BG_CYAN = 46;
    final public const BG_GREY = 47;

    final public const RESET = 0;
    final public const NORMAL = 0;
    final public const BOLD = 1;
    final public const ITALIC = 3;
    final public const UNDERLINE = 4;
    final public const BLINK = 5;
    final public const NEGATIVE = 7;
    final public const CONCEALED = 8;
    final public const CROSSED_OUT = 9;
    final public const FRAMED = 51;
    final public const ENCIRCLED = 52;
    final public const OVERLINED = 53;


    /**
     * Will return a string formatted with the given ANSI style
     *
     * @param string $string the string to be formatted
     * @param array  $format An array containing formatting values.
     * You can pass any of the FG_*, BG_* and TEXT_* constants
     * and also [[xtermFgColor]] and [[xtermBgColor]] to specify a format.
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
     */
    public static function streamSupportsAnsiColors(mixed $stream): bool
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
    public static function stdout(mixed $string, ...$args): bool|int
    {
        return static::writeToStream(\STDOUT, (string) $string, ...$args);
    }

    /**
     * Prints a string to STDERR.
     *
     * @param string $string the string to print
     * @return int|boolean Number of bytes printed or false on error
     */
    public static function stderr(mixed $string, ...$args): bool|int
    {
        return static::writeToStream(\STDERR, (string) $string, ...$args);
    }

    public static function writeToStream($stream, $string, ...$args): bool|int
    {
        if (!empty($args) && static::streamSupportsAnsiColors($stream)) {
            $string = static::ansiFormat($string, $args);
        }
        return \fwrite($stream, (string) $string);
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
     * @param string|null $string the text to print
     * @return integer|boolean number of bytes printed or false on error.
     */
    public static function output(string $string = null): bool|int
    {
        return static::stdout($string . \PHP_EOL);
    }

    /**
     * Prints text to STDERR appended with a carriage return (PHP_EOL).
     *
     * @param string|null $string the text to print
     * @return integer|boolean number of bytes printed or false on error.
     */
    public static function error(string $string = null): bool|int
    {
        return static::stderr($string . \PHP_EOL);
    }

    /**
     * Asks user to confirm by typing y or n.
     *
     * @param string $message to print out before waiting for user input
     * @param boolean $default this value is returned if no selection is made.
     * @return bool whether user confirmed
     */
    public static function confirm(string $message, bool $default = false): bool
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
