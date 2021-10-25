<?php declare(strict_types=1);

namespace mii\util;

use mii\core\Exception;

class Text
{
    public const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * Limits a phrase to a given number of words.
     *
     *     $text = Text::limit_words($text);
     *
     * @param string $str phrase to limit words of
     * @param integer $limit number of words to limit to
     * @param string $end_char end character or entity
     * @return  string
     */
    public static function limitWords(string $str, int $limit = 100, string $end_char = '…'): string
    {
        if (\trim($str) === '') {
            return $str;
        }

        if ($limit <= 0) {
            return $end_char;
        }

        \preg_match('/^\s*+(?:\S++\s*+){1,' . $limit . '}/u', $str, $matches);

        // Only attach the end character if the matched string is shorter
        // than the starting string.
        return \rtrim($matches[0]) . ((\strlen($matches[0]) === \strlen($str)) ? '' : $end_char);
    }

    /**
     * Limits a phrase to a given number of characters.
     *
     *     $text = Text::limit_chars($text);
     *
     * @param string $str phrase to limit characters of
     * @param integer $limit number of characters to limit to
     * @param string $end_char end character or entity
     * @param boolean $preserve_words enable or disable the preservation of words while limiting
     */
    public static function limitChars(string $str, int $limit = 100, string $end_char = '…', bool $preserve_words = false): string
    {
        if (!$str) {
            return '';
        }

        if (\trim($str) === '' || \mb_strlen($str) <= $limit) {
            return $str;
        }

        if ($limit <= 0) {
            return $end_char;
        }

        if ($preserve_words === false) {
            return \rtrim(\mb_substr($str, 0, $limit)) . $end_char;
        }

        // Don't preserve words. The limit is considered the top limit.
        // No strings with a length longer than $limit should be returned.
        if (!\preg_match('/^.{0,' . $limit . '}\s/us', $str, $matches)) {
            return $end_char;
        }

        return \rtrim($matches[0]) . ((\strlen($matches[0]) === \strlen($str)) ? '' : $end_char);
    }


    /**
     * Generates a random string of a given type and length.
     *
     * @param string|null $type a type of pool, or a string of characters to use as the pool
     * @param integer $length length of string to return
     * @return  string
     */
    public static function random(string $type = null, int $length = 8): string
    {
        if ($type === null) {
            // Default is to generate an alphanumeric string
            $type = 'alnum';
        }

        $pool = match ($type) {
            'alnum' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'hexdec' => '0123456789abcdef',
            default => (string)$type,
        };

        // Split the pool into an array of characters
        $pool = \str_split($pool, 1);

        // Largest pool key
        $max = \count($pool) - 1;

        $str = '';
        for ($i = 0; $i < $length; $i++) {
            // Select a random character from the pool and add it to the string
            $str .= $pool[\mt_rand(0, $max)];
        }

        // Make sure alnum strings contain at least one letter and one digit
        if ($type === 'alnum' && $length > 1) {
            if (\ctype_alpha($str)) {
                // Add a random digit
                $str[\mt_rand(0, $length - 1)] = \chr(\mt_rand(48, 57));
            } elseif (\ctype_digit($str)) {
                // Add a random letter
                $str[\mt_rand(0, $length - 1)] = \chr(\mt_rand(65, 90));
            }
        }

        return $str;
    }

    /**
     * А unicode-safe implementation of built-in PHP function `ucfirst()`
     *
     * @param string $string string to transform
     * @return  string
     */
    public static function ucfirst(string $string): string
    {
        $first = \mb_substr($string, 0, 1);
        $rest = \mb_substr($string, 1);

        return \mb_strtoupper($first) . $rest;
    }


    /**
     * Automatically applies "p" and "br" markup to text.
     * Basically [nl2br](http://php.net/nl2br) on steroids.
     *
     *     echo Text::auto_p($text);
     *
     * [!!] This method is not foolproof since it uses regex to parse HTML.
     *
     * @param string  $str subject
     * @param boolean $br convert single linebreaks to <br />
     * @return  string
     */
    public static function autoP(string $str, bool $br = true): string
    {
        // Trim whitespace
        if (($str = \trim($str)) === '') {
            return '';
        }

        // Standardize newlines
        $str = \str_replace(["\r\n", "\r"], "\n", $str);

        // Trim whitespace on each line
        $str = \preg_replace('~^[ \t]+~m', '', $str);
        $str = \preg_replace('~[ \t]+$~m', '', $str);

        // The following regexes only need to be executed if the string contains html
        if ($html_found = \str_contains($str, '<')) {
            // Elements that should not be surrounded by p tags
            $no_p = '(?:p|div|h[1-6r]|ul|ol|li|blockquote|d[dlt]|pre|t[dhr]|t(?:able|body|foot|head)|c(?:aption|olgroup)|form|s(?:elect|tyle)|a(?:ddress|rea)|ma(?:p|th))';

            // Put at least two linebreaks before and after $no_p elements
            $str = \preg_replace('~^<' . $no_p . '[^>]*+>~im', "\n$0", $str);
            $str = \preg_replace('~</' . $no_p . '\s*+>$~im', "$0\n", $str);
        }

        // Do the <p> magic!
        $str = '<p>' . \trim($str) . '</p>';
        $str = \preg_replace('~\n{2,}~', "</p>\n\n<p>", $str);

        // The following regexes only need to be executed if the string contains html
        if ($html_found !== false) {
            // Remove p tags around $no_p elements
            $str = \preg_replace('~<p>(?=</?' . $no_p . '[^>]*+>)~i', '', $str);
            $str = \preg_replace('~(</?' . $no_p . '[^>]*+>)</p>~i', '$1', $str);
        }

        // Convert single linebreaks to <br />
        if ($br === true) {
            $str = \preg_replace('~(?<!\n)\n(?!\n)~', "<br />\n", $str);
        }

        return $str;
    }

    /**
     * Prevents [widow words](http://www.shauninman.com/archive/2006/08/22/widont_wordpress_plugin)
     * by inserting a non-breaking space between the last two words.
     *
     *     echo Text::widont($text);
     *
     * @param string $str text to remove widows from
     * @return  string
     */
    public static function widont(string $str): string
    {
        $str = \rtrim($str);
        $space = \strrpos($str, ' ');

        if ($space !== false) {
            $str = \substr($str, 0, $space) . '&nbsp;' . \substr($str, $space + 1);
        }

        return $str;
    }

    /**
     * Convert a phrase to a URL-safe title.
     *
     *     echo Text::title('Мой блог пост'); // "moi-blog-post"
     *
     * @param string $text
     * @param string $separator Word separator (any single character)
     * @return string
     */
    public static function toSlug(string $text, string $separator = '-'): string
    {
        $value = UTF8::ruTranslit($text);

        // Transliterate value to ASCII
        $value = UTF8::transliterateToAscii($value);

        $value = UTF8::stripNonAscii($value);

        // Set preserved characters
        $preserved_characters = \preg_quote($separator, '!');

        // Remove all characters that are not in preserved characters, a-z, 0-9, point or whitespace
        $value = \preg_replace('![^' . $preserved_characters . 'a-z0-9.\s]+!', '', \strtolower($value));

        // Replace all separator characters and whitespace by a single separator
        $value = \preg_replace('![' . $preserved_characters . '\s]+!u', $separator, $value);

        // Trim separators from the beginning and end
        return \trim($value, $separator);
    }


    public static function b64Encode($data): string
    {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }


    public static function b64Decode($data): string
    {
        return \base64_decode(\strtr($data, '-_', '+/'));
    }


    /**
     * Declination of number
     * @param int $number
     * @param mixed $array
     * @return mixed
     */
    public static function decl(int $number, array $array)
    {
        $cases = [2, 0, 1, 1, 1, 2];

        if ($number % 100 > 4 && $number % 100 < 20) {
            return $array[2];
        }

        return $array[$cases[\min($number % 10, 5)]];
    }


    public static array $byte_units = [
        'B' => 0,
        'K' => 10,
        'KB' => 10,
        'M' => 20,
        'MB' => 20,
        'G' => 30,
        'GB' => 30,
    ];

    /**
     * Converts a file size number to a byte value. File sizes are defined in
     * the format: SB, where S is the size (1, 8.5, 300, etc.) and B is the
     * byte unit (K, Mb, GB, etc.). All valid byte units are defined in
     * Num::$byte_units
     *
     * @param string $size file size in SB format
     * @return  float
     * @throws Exception
     */
    public static function bytes(string $size)
    {
        // Prepare the size
        $size = \trim($size);
        // Construct an OR list of byte units for the regex
        $accepted = \implode('|', \array_keys(self::$byte_units));
        // Construct the regex pattern for verifying the size format
        $pattern = '/^([0-9]+(?:\.[0-9]+)?)(' . $accepted . ')?$/Di';
        // Verify the size format and store the matching parts
        if (!\preg_match($pattern, $size, $matches)) {
            throw new Exception('The byte unit size, "' . $size . '", is improperly formatted.');
        }
        // Find the float value of the size
        $size = (float) $matches[1];
        // Find the actual unit, assume B if no unit specified
        $unit = $matches[2] ?? 'B';
        // Convert the size into bytes
        return $size * (2 ** self::$byte_units[$unit]);
    }


    public static function UUIDv4(): string
    {
        $data = \random_bytes(16);
        $data[6] = \chr(\ord($data[6]) & 0x0f | 0x40);
        $data[8] = \chr(\ord($data[8]) & 0x3f | 0x80);
        return $data;
    }

    public static function genUUID(): string
    {
        return self::b64Encode(self::UUIDv4());
    }

    public static function firstLetters(array $words): string
    {
        $fl = '';
        for ($i = 0; $i < 2; $i++) {
            if (isset($words[$i])) {
                $fl .= \mb_substr($words[$i], 0, 1);
            }
        }

        return \mb_strtoupper($fl);
    }
}
