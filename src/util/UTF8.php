<?php

/**
 * A port of [phputf8](http://phputf8.sourceforge.net/) to a unified set
 * of files. Provides multi-byte aware replacement string functions.
 *
 * For UTF-8 support to work correctly, the following requirements must be met:
 *
 * - PCRE needs to be compiled with UTF-8 support (--enable-utf8)
 * - Support for [Unicode properties](http://php.net/manual/reference.pcre.pattern.modifiers.php)
 *   is highly recommended (--enable-unicode-properties)
 * - UTF-8 conversion will be much more reliable if the
 *   [iconv extension](http://php.net/iconv) is loaded
 * - The [mbstring extension](http://php.net/mbstring) is highly recommended,
 *   but must not be overloading string functions
 *
 * [!!] This file is licensed differently from the rest of Kohana. As a port of
 * [phputf8](http://phputf8.sourceforge.net/), this file is released under the LGPL.
 *
 * @package    Kohana
 * @category   Base
 * @author     Kohana Team
 * @copyright  (c) 2007-2012 Kohana Team
 * @copyright  (c) 2005 Harry Fuecks
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt
 */

namespace mii\util;

class UTF8
{


    /**
     * Recursively cleans arrays, objects, and strings. Removes ASCII control
     * codes and converts to the requested charset while silently discarding
     * incompatible characters.
     *
     *     UTF8::clean($_GET); // Clean GET data
     *
     * [!!] This method requires [Iconv](http://php.net/iconv)
     *
     * @param   mixed $var variable to clean
     * @param   string $charset character set, defaults to Kohana::$charset
     * @return  mixed
     * @uses    UTF8::strip_ascii_ctrl
     * @uses    UTF8::is_ascii
     */
    public static function clean($var, $charset = NULL) {
        if (!$charset) {
            // Use the application character set
            $charset = \Mii::$app->charset;
        }

        if (is_array($var) OR is_object($var)) {
            foreach ($var as $key => $val) {
                // Recursion!
                $var[self::clean($key)] = self::clean($val);
            }
        } elseif (is_string($var) AND $var !== '') {
            // Remove control characters
            $var = self::strip_ascii_ctrl($var);

            if (!self::is_ascii($var)) {
                // Disable notices
                $error_reporting = error_reporting(~E_NOTICE);

                // iconv is expensive, so it is only used when needed
                $var = iconv($charset, $charset . '//IGNORE', $var);

                // Turn notices back on
                error_reporting($error_reporting);
            }
        }

        return $var;
    }

    /**
     * Tests whether a string contains only 7-bit ASCII bytes. This is used to
     * determine when to use native functions or UTF-8 functions.
     *
     *     $ascii = UTF8::is_ascii($str);
     *
     * @param   mixed $str string or array of strings to check
     * @return  boolean
     */
    public static function is_ascii($str) {
        if (is_array($str)) {
            $str = implode($str);
        }

        return !preg_match('/[^\x00-\x7F]/S', $str);
    }

    /**
     * Strips out device control codes in the ASCII range.
     *
     *     $str = UTF8::strip_ascii_ctrl($str);
     *
     * @param   string $str string to clean
     * @return  string
     */
    public static function strip_ascii_ctrl($str) {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', $str);
    }

    /**
     * Strips out all non-7bit ASCII bytes.
     *
     *     $str = UTF8::strip_non_ascii($str);
     *
     * @param   string $str string to clean
     * @return  string
     */
    public static function strip_non_ascii($str) {
        return preg_replace('/[^\x00-\x7F]+/S', '', $str);
    }

    /**
     * Replaces special/accented UTF-8 characters by ASCII-7 "equivalents".
     *
     *     $ascii = UTF8::transliterate_to_ascii($utf8);
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @param   string $str string to transliterate
     * @param   integer $case -1 lowercase only, +1 uppercase only, 0 both cases
     * @return  string
     */
    public static function transliterate_to_ascii($str, $case = 0) {
        static $utf8_lower_accents = NULL;
        static $utf8_upper_accents = NULL;

        if ($case <= 0) {
            if ($utf8_lower_accents === NULL) {
                $utf8_lower_accents = array(
                    'à' => 'a', 'ô' => 'o', 'ď' => 'd', 'ḟ' => 'f', 'ë' => 'e', 'š' => 's', 'ơ' => 'o',
                    'ß' => 'ss', 'ă' => 'a', 'ř' => 'r', 'ț' => 't', 'ň' => 'n', 'ā' => 'a', 'ķ' => 'k',
                    'ŝ' => 's', 'ỳ' => 'y', 'ņ' => 'n', 'ĺ' => 'l', 'ħ' => 'h', 'ṗ' => 'p', 'ó' => 'o',
                    'ú' => 'u', 'ě' => 'e', 'é' => 'e', 'ç' => 'c', 'ẁ' => 'w', 'ċ' => 'c', 'õ' => 'o',
                    'ṡ' => 's', 'ø' => 'o', 'ģ' => 'g', 'ŧ' => 't', 'ș' => 's', 'ė' => 'e', 'ĉ' => 'c',
                    'ś' => 's', 'î' => 'i', 'ű' => 'u', 'ć' => 'c', 'ę' => 'e', 'ŵ' => 'w', 'ṫ' => 't',
                    'ū' => 'u', 'č' => 'c', 'ö' => 'o', 'è' => 'e', 'ŷ' => 'y', 'ą' => 'a', 'ł' => 'l',
                    'ų' => 'u', 'ů' => 'u', 'ş' => 's', 'ğ' => 'g', 'ļ' => 'l', 'ƒ' => 'f', 'ž' => 'z',
                    'ẃ' => 'w', 'ḃ' => 'b', 'å' => 'a', 'ì' => 'i', 'ï' => 'i', 'ḋ' => 'd', 'ť' => 't',
                    'ŗ' => 'r', 'ä' => 'a', 'í' => 'i', 'ŕ' => 'r', 'ê' => 'e', 'ü' => 'u', 'ò' => 'o',
                    'ē' => 'e', 'ñ' => 'n', 'ń' => 'n', 'ĥ' => 'h', 'ĝ' => 'g', 'đ' => 'd', 'ĵ' => 'j',
                    'ÿ' => 'y', 'ũ' => 'u', 'ŭ' => 'u', 'ư' => 'u', 'ţ' => 't', 'ý' => 'y', 'ő' => 'o',
                    'â' => 'a', 'ľ' => 'l', 'ẅ' => 'w', 'ż' => 'z', 'ī' => 'i', 'ã' => 'a', 'ġ' => 'g',
                    'ṁ' => 'm', 'ō' => 'o', 'ĩ' => 'i', 'ù' => 'u', 'į' => 'i', 'ź' => 'z', 'á' => 'a',
                    'û' => 'u', 'þ' => 'th', 'ð' => 'dh', 'æ' => 'ae', 'µ' => 'u', 'ĕ' => 'e', 'ı' => 'i',
                );
            }

            $str = str_replace(
                array_keys($utf8_lower_accents),
                array_values($utf8_lower_accents),
                $str
            );
        }

        if ($case >= 0) {
            if ($utf8_upper_accents === NULL) {
                $utf8_upper_accents = array(
                    'À' => 'A', 'Ô' => 'O', 'Ď' => 'D', 'Ḟ' => 'F', 'Ë' => 'E', 'Š' => 'S', 'Ơ' => 'O',
                    'Ă' => 'A', 'Ř' => 'R', 'Ț' => 'T', 'Ň' => 'N', 'Ā' => 'A', 'Ķ' => 'K', 'Ĕ' => 'E',
                    'Ŝ' => 'S', 'Ỳ' => 'Y', 'Ņ' => 'N', 'Ĺ' => 'L', 'Ħ' => 'H', 'Ṗ' => 'P', 'Ó' => 'O',
                    'Ú' => 'U', 'Ě' => 'E', 'É' => 'E', 'Ç' => 'C', 'Ẁ' => 'W', 'Ċ' => 'C', 'Õ' => 'O',
                    'Ṡ' => 'S', 'Ø' => 'O', 'Ģ' => 'G', 'Ŧ' => 'T', 'Ș' => 'S', 'Ė' => 'E', 'Ĉ' => 'C',
                    'Ś' => 'S', 'Î' => 'I', 'Ű' => 'U', 'Ć' => 'C', 'Ę' => 'E', 'Ŵ' => 'W', 'Ṫ' => 'T',
                    'Ū' => 'U', 'Č' => 'C', 'Ö' => 'O', 'È' => 'E', 'Ŷ' => 'Y', 'Ą' => 'A', 'Ł' => 'L',
                    'Ų' => 'U', 'Ů' => 'U', 'Ş' => 'S', 'Ğ' => 'G', 'Ļ' => 'L', 'Ƒ' => 'F', 'Ž' => 'Z',
                    'Ẃ' => 'W', 'Ḃ' => 'B', 'Å' => 'A', 'Ì' => 'I', 'Ï' => 'I', 'Ḋ' => 'D', 'Ť' => 'T',
                    'Ŗ' => 'R', 'Ä' => 'A', 'Í' => 'I', 'Ŕ' => 'R', 'Ê' => 'E', 'Ü' => 'U', 'Ò' => 'O',
                    'Ē' => 'E', 'Ñ' => 'N', 'Ń' => 'N', 'Ĥ' => 'H', 'Ĝ' => 'G', 'Đ' => 'D', 'Ĵ' => 'J',
                    'Ÿ' => 'Y', 'Ũ' => 'U', 'Ŭ' => 'U', 'Ư' => 'U', 'Ţ' => 'T', 'Ý' => 'Y', 'Ő' => 'O',
                    'Â' => 'A', 'Ľ' => 'L', 'Ẅ' => 'W', 'Ż' => 'Z', 'Ī' => 'I', 'Ã' => 'A', 'Ġ' => 'G',
                    'Ṁ' => 'M', 'Ō' => 'O', 'Ĩ' => 'I', 'Ù' => 'U', 'Į' => 'I', 'Ź' => 'Z', 'Á' => 'A',
                    'Û' => 'U', 'Þ' => 'Th', 'Ð' => 'Dh', 'Æ' => 'Ae', 'İ' => 'I',
                );
            }

            $str = str_replace(
                array_keys($utf8_upper_accents),
                array_values($utf8_upper_accents),
                $str
            );
        }

        return $str;

    }


    /**
     * Makes a UTF-8 string's first character uppercase. This is a UTF8-aware
     * version of [ucfirst](http://php.net/ucfirst).
     *
     *     $str = UTF8::ucfirst($str);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string $str mixed case string
     * @return  string
     */
    public static function ucfirst($str) {
        if (UTF8::is_ascii($str))
            return ucfirst($str);

        preg_match('/^(.?)(.*)$/us', $str, $matches);
        return mb_strtoupper($matches[1], \Mii::$app->charset) . $matches[2];
    }


    /**
     * Strips whitespace (or other UTF-8 characters) from the beginning and
     * end of a string. This is a UTF8-aware version of [trim](http://php.net/trim).
     *
     *     $str = UTF8::trim($str);
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @param   string $str input string
     * @param   string $charlist string of characters to remove
     * @return  string
     */
    public static function trim($str, $charlist = NULL) {
        return self::_trim($str, $charlist);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the beginning of
     * a string. This is a UTF8-aware version of [ltrim](http://php.net/ltrim).
     *
     *     $str = UTF8::ltrim($str);
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @param   string $str input string
     * @param   string $charlist string of characters to remove
     * @return  string
     */
    public static function ltrim($str, $charlist = NULL) {
        if ($charlist === NULL)
            return ltrim($str);

        if (UTF8::is_ascii($charlist))
            return ltrim($str, $charlist);

        $charlist = preg_replace('#[-\[\]:\\\\^/]#', '\\\\$0', $charlist);

        return preg_replace('/^[' . $charlist . ']+/u', '', $str);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the end of a string.
     * This is a UTF8-aware version of [rtrim](http://php.net/rtrim).
     *
     *     $str = UTF8::rtrim($str);
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @param   string $str input string
     * @param   string $charlist string of characters to remove
     * @return  string
     */
    public static function rtrim($str, $charlist = NULL) {
        if ($charlist === NULL)
            return rtrim($str);

        if (UTF8::is_ascii($charlist))
            return rtrim($str, $charlist);

        $charlist = preg_replace('#[-\[\]:\\\\^/]#', '\\\\$0', $charlist);

        return preg_replace('/[' . $charlist . ']++$/uD', '', $str);
    }


    public static function _trim($str, $charlist = NULL) {
        if ($charlist === NULL)
            return trim($str);

        return UTF8::ltrim(UTF8::rtrim($str, $charlist), $charlist);
    }


    public static function ru_translit($str) {
        static $trans_table = null;

        if ($trans_table === null) {
            $trans_table = [
                'а' => 'a', 'б' => 'b', 'в' => 'v',
                'г' => 'g', 'д' => 'd', 'е' => 'e',
                'ё' => 'e', 'ж' => 'zh', 'з' => 'z',
                'и' => 'i', 'й' => 'y', 'к' => 'k',
                'л' => 'l', 'м' => 'm', 'н' => 'n',
                'о' => 'o', 'п' => 'p', 'р' => 'r',
                'с' => 's', 'т' => 't', 'у' => 'u',
                'ф' => 'f', 'х' => 'h', 'ц' => 'c',
                'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
                'ь' => "'", 'ы' => 'y', 'ъ' => "'",
                'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

                'А' => 'A', 'Б' => 'B', 'В' => 'V',
                'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
                'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z',
                'И' => 'I', 'Й' => 'Y', 'К' => 'K',
                'Л' => 'L', 'М' => 'M', 'Н' => 'N',
                'О' => 'O', 'П' => 'P', 'Р' => 'R',
                'С' => 'S', 'Т' => 'T', 'У' => 'U',
                'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
                'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch',
                'Ь' => "'", 'Ы' => 'Y', 'Ъ' => "'",
                'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
            ];
        }

        $str = str_replace(
            array_keys($trans_table),
            array_values($trans_table),
            $str
        );

        return $str;
    }

}
