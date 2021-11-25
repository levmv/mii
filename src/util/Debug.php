<?php /** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace mii\util;

/**
 * Contains debugging and dumping tools.
 *
 * @copyright  (c) 2008-2012 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Debug
{

    /**
     * Returns an HTML string of information about a single variable.
     *
     * Borrows heavily on concepts from the Debug class of [Nette](http://nettephp.com/).
     *
     * @param mixed   $value variable to dump
     * @param integer $length maximum length of strings
     * @param integer $level_recursion recursion limit
     * @return  string
     */
    public static function dump(mixed $value, int $length = 128, int $level_recursion = 10): string
    {
        return self::_dump($value, $length, $level_recursion);
    }

    /**
     * Helper for Debug::dump(), handles recursion in arrays and objects.
     *
     * @param mixed   $var variable to dump
     * @param integer $length maximum length of strings
     * @param integer $limit recursion limit
     * @param integer $level current recursion level (internal usage only!)
     * @return  string
     */
    protected static function _dump(mixed &$var, int $length = 128, int $limit = 10, int $level = 0): string
    {
        if ($var === null) {
            return '<small>NULL</small>';
        } elseif (\is_bool($var)) {
            return '<small>bool</small> ' . ($var ? 'TRUE' : 'FALSE');
        } elseif (\is_int($var)) {
            return "<small>int</small> $var";
        } elseif (\is_float($var)) {
            return "<small>float</small> $var";
        } elseif (\is_resource($var)) {
            if (($type = \get_resource_type($var)) === 'stream') {
                $meta = \stream_get_meta_data($var);

                if (isset($meta['uri'])) {
                    $file = $meta['uri'];

                    if (\stream_is_local($file)) {
                        $file = Debug::path($file);
                    }

                    return "<small>resource</small><span>($type)</span> " . \htmlspecialchars($file, \ENT_NOQUOTES, 'utf-8');
                }
            } else {
                return "<small>resource</small><span>($type)</span>";
            }
        } elseif (\is_string($var)) {
            // Clean invalid multibyte characters. iconv is only invoked
            // if there are non ASCII characters in the string, so this
            // isn't too much of a hit.
            // Remove control characters
            $var = UTF8::stripAsciiCtrl($var);

            if (!UTF8::isAscii($var)) {
                // Disable notices
                $error_reporting = \error_reporting(~\E_NOTICE);
                // iconv is expensive, so it is only used when needed
                $var = \iconv('UTF-8', 'UTF-8//IGNORE', $var);
                // Turn notices back on
                \error_reporting($error_reporting);
            }

            if (\mb_strlen($var) > $length) {
                // Encode the truncated string
                $str = \htmlspecialchars(\mb_substr($var, 0, $length), \ENT_NOQUOTES, 'utf-8') . '&nbsp;&hellip;';
            } else {
                // Encode the string
                $str = \htmlspecialchars($var, \ENT_NOQUOTES, 'utf-8');
            }

            return '<small>string</small><span>(' . \strlen($var) . ')</span> "' . $str . '"';
        } elseif (\is_array($var)) {
            $output = [];

            // Indentation for this variable
            $space = \str_repeat($s = '    ', $level);

            static $marker;

            if ($marker === null) {
                // Make a unique marker
                $marker = \uniqid("\x00", false);
            }

            if (empty($var)) {
                // Do nothing
            } elseif (isset($var[$marker])) {
                $output[] = "(\n$space$s*RECURSION*\n$space)";
            } elseif ($level < $limit) {
                $output[] = '<span>(';

                $var[$marker] = true;
                foreach ($var as $key => &$val) {
                    if ($key === $marker) {
                        continue;
                    }
                    if (!\is_int($key)) {
                        $key = '"' . \htmlspecialchars($key, \ENT_NOQUOTES, 'utf-8') . '"';
                    }

                    $output[] = "$space$s$key => " . Debug::_dump($val, $length, $limit, $level + 1);
                }
                unset($var[$marker]);

                $output[] = "$space)</span>";
            } else {
                // Depth too great
                $output[] = "(\n$space$s...\n$space)";
            }

            return '<small>array</small><span>(' . \count($var) . ')</span> ' . \implode("\n", $output);
        } elseif (\is_object($var)) {
            // Copy the object as an array
            $array = (array) $var;

            $output = [];

            // Indentation for this variable
            $space = \str_repeat($s = '    ', $level);

            $hash = \spl_object_hash($var);

            // Objects that are being dumped
            static $objects = [];

            if (empty($var)) {
                // Do nothing
            } elseif (isset($objects[$hash])) {
                $output[] = "{\n$space$s*RECURSION*\n$space}";
            } elseif ($level < $limit) {
                $output[] = '<code>{';

                $objects[$hash] = true;
                foreach ($array as $key => &$val) {
                    if (!\is_int($key) && $key[0] === "\x00") {
                        // Determine if the access is protected or protected
                        $access = '<small>' . (($key[1] === '*') ? 'protected' : 'private') . '</small>';

                        // Remove the access level from the variable name
                        $key = \substr($key, \strrpos($key, "\x00") + 1);
                    } else {
                        $access = '<small>public</small>';
                    }

                    $output[] = "$space$s$access $key => " . Debug::_dump($val, $length, $limit, $level + 1);
                }
                unset($objects[$hash]);

                $output[] = "$space}</code>";
            } else {
                // Depth too great
                $output[] = "{\n$space$s...\n$space}";
            }

            return '<small>object</small> <span>' . \get_class($var) . '(' . \count($array) . ')</span> ' . \implode("\n", $output);
        }

        return '<small>' . \get_debug_type($var) . '</small> ' . \htmlspecialchars(\print_r($var, true), \ENT_NOQUOTES, 'utf-8');
    }

    /**
     * Removes standart paths (Mii::$paths) from a filename,
     * replacing them with the aliases. Useful for debugging
     * when you want to display a shorter path.
     *
     * @param string $file path to debug
     * @return  string
     */
    public static function path(string $file): string
    {
        if (!isset(\Mii::$paths['mii'])) {
            if (!isset(\Mii::$paths['vendor'])) {
                \Mii::$paths['vendor'] = path('root') . '/vendor';
            }

            \Mii::$paths['mii'] = path('vendor') . '/levmorozov/mii/src';
            \uasort(\Mii::$paths, static function ($a, $b) {
                return \strlen($b) - \strlen($a);
            });
        }
        foreach (\Mii::$paths as $name => $path) {
            if (\str_starts_with($file, $path)) {
                $file = '{' . $name . '}' . \substr($file, \strlen($path));
                break;
            }
        }

        return $file;
    }

    /**
     * Returns an HTML string, highlighting a specific line of a file, with some
     * number of lines padded above and below.
     *
     *     // Highlights the current line of the current file
     *     echo Debug::source(__FILE__, __LINE__);
     *
     * @param string $file file to open
     * @param integer $line_number line number to highlight
     * @param integer $padding number of padding lines
     * @return  string   source of file
     * @return  FALSE    file is unreadable
     */
    public static function source(string $file, int $line_number, int $padding = 5)
    {
        if (!$file || !\is_readable($file)) {
            // Continuing will cause errors
            return false;
        }

        // Open the file and set the line position
        $file = \fopen($file, 'r');
        $line = 0;

        // Set the reading range
        $range = ['start' => $line_number - $padding, 'end' => $line_number + $padding];

        // Set the zero-padding amount for line numbers
        $format = '% ' . \strlen((string) $range['end']) . 'd';

        $source = '';
        while (($row = \fgets($file)) !== false) {
            // Increment the line number
            if (++$line > $range['end']) {
                break;
            }

            if ($line >= $range['start']) {
                // Make the row safe for output
                $row = \htmlspecialchars($row, \ENT_NOQUOTES, 'utf-8');

                // Trim whitespace and sanitize the row
                $row = '<span class="number">' . \sprintf($format, $line) . '</span> ' . $row;

                if ($line === $line_number) {
                    // Apply highlighting to this row
                    $row = '<span class="line highlight">' . $row . '</span>';
                } else {
                    $row = '<span class="line">' . $row . '</span>';
                }

                // Add to the captured source
                $source .= $row;
            }
        }

        // Close the file
        \fclose($file);
        //$source = highlight_string($source);

        return '<pre class="source"><code>' . $source . '</code></pre>';
    }

    /**
     * Returns an array of HTML strings that represent each step in the backtrace.
     *
     *     // Displays the entire current backtrace
     *     echo implode('<br/>', Debug::trace());
     *
     * @param array|null $trace
     * @return  array
     * @throws \ReflectionException
     */
    public static function trace(array $trace = null): array
    {
        if ($trace === null) {
            // Start a new trace
            $trace = \debug_backtrace();
        }

        // Non-standard function calls
        $statements = ['include', 'include_once', 'require', 'require_once'];

        $output = [];
        foreach ($trace as $step) {
            if (!isset($step['function'])) {
                // Invalid trace step
                continue;
            }

            if (isset($step['file']) and isset($step['line'])) {
                // Include the source of this step
                $source = Debug::source($step['file'], $step['line']);
            }

            if (isset($step['file'])) {
                $file = $step['file'];

                if (isset($step['line'])) {
                    $line = $step['line'];
                }
            }

            // function()
            $function = $step['function'];

            if (\in_array($step['function'], $statements, true)) {
                if (empty($step['args'])) {
                    // No arguments
                    $args = [];
                } else {
                    // Sanitize the file path
                    $args = [$step['args'][0]];
                }
            } elseif (isset($step['args'])) {
                if (!\function_exists($step['function']) || \str_contains($step['function'], '{closure}')) {
                    // Introspection on closures or language constructs in a stack trace is impossible
                    $params = null;
                } else {
                    if (isset($step['class'])) {
                        if (\method_exists($step['class'], $step['function'])) {
                            $reflection = new \ReflectionMethod($step['class'], $step['function']);
                        } else {
                            $reflection = new \ReflectionMethod($step['class'], '__call');
                        }
                    } else {
                        $reflection = new \ReflectionFunction($step['function']);
                    }

                    // Get the function parameters
                    $params = $reflection->getParameters();
                }

                $args = [];

                foreach ($step['args'] as $i => $arg) {
                    if (isset($params[$i])) {
                        // Assign the argument by the parameter name
                        $args[$params[$i]->name] = $arg;
                    } else {
                        // Assign the argument by number
                        $args[$i] = $arg;
                    }
                }
            }

            if (isset($step['class'])) {
                // Class->method() or Class::method()
                $function = $step['class'] . $step['type'] . $step['function'];
            }

            $output[] = [
                'function' => $function,
                'args' => $args ?? null,
                'file' => $file ?? null,
                'line' => $line ?? null,
                'source' => $source ?? null,
            ];

            unset($function, $args, $file, $line, $source);
        }

        return $output;
    }


    public static function shortTextTrace(array $trace = null)
    {
        $trace = static::trace($trace);

        $count = 0;

        return \implode("\n", \array_map(static function ($step) use (&$count) {
            $file = $step['file'] ? Debug::path($step['file']) : 'PHP internal call';
            $line = $step['line'];

            $args = [];
            if (\is_array($step['args'])) {
                foreach ($step['args'] as $arg) {
                    if (\is_string($arg)) {
                        $args[] = "'" . Text::limitChars($arg, 45) . "'";
                    } elseif (\is_array($arg)) {
                        $args[] = 'Array';
                    } elseif (\is_null($arg)) {
                        $args[] = 'null';
                    } elseif (\is_bool($arg)) {
                        $args[] = ($arg) ? 'true' : 'false';
                    } elseif (\is_object($arg)) {
                        $args[] = \get_class($arg);
                    } elseif (\is_resource($arg)) {
                        $args[] = \get_resource_type($arg);
                    } else {
                        $args[] = $arg;
                    }
                }
            }

            $count++;

            return "$count {$file}[$line]: " . $step['function'] . '(' . \implode(', ', $args) . ')';
        }, $trace));
    }
}
