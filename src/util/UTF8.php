<?php declare(strict_types=1);

namespace mii\util;

class UTF8
{

    /**
     * Replaces all 4-bytes characters in utf8 string
     *
     * Note: it's maybe a good idea to use "\xEF\xBF\xBD" for $replace
     */
    public static function strip4b(string $str, string $replace = ''): string
    {
        return \preg_replace('/[\xF0-\xF7].../s', $replace, $str);
    }

    /**
     * Tests whether a string contains only 7-bit ASCII bytes. This is used to
     * determine when to use native functions or UTF-8 functions.
     */
    public static function isAscii(string $str): bool
    {
        return !\preg_match('/[^\x00-\x7F]/S', $str);
    }

    /**
     * Strips out device control codes in the ASCII range.
     */
    public static function stripAsciiCtrl(string $str): string
    {
        return \preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', $str);
    }

    /**
     * Strips out all non-7bit ASCII bytes.
     */
    public static function stripNonAscii(string $str): string
    {
        return \preg_replace('/[^\x00-\x7F]+/S', '', $str);
    }

    /**
     * Replaces special/accented UTF-8 characters by ASCII-7 "equivalents".
     *
     *     $ascii = UTF8::transliterate_to_ascii($utf8);
     *
     * @param string $str string to transliterate
     * @param integer $case -1 lowercase only, +1 uppercase only, 0 both cases
     * @return  string
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    public static function transliterateToAscii(string $str, int $case = 0)
    {
        static $utf8_lower_accents = null;
        static $utf8_upper_accents = null;

        if ($case <= 0) {
            if ($utf8_lower_accents === null) {
                $utf8_lower_accents = [
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
                ];
            }

            $str = \str_replace(
                \array_keys($utf8_lower_accents),
                \array_values($utf8_lower_accents),
                $str
            );
        }

        if ($case >= 0) {
            if ($utf8_upper_accents === null) {
                $utf8_upper_accents = [
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
                ];
            }

            $str = \str_replace(
                \array_keys($utf8_upper_accents),
                \array_values($utf8_upper_accents),
                $str
            );
        }

        return $str;
    }


    /**
     * Makes a UTF-8 string's first character uppercase. This is a UTF8-aware
     * version of [ucfirst](http://php.net/ucfirst).
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     */
    public static function ucfirst(string $str): string
    {
        if (self::isAscii($str)) {
            return \ucfirst($str);
        }

        \preg_match('/^(.?)(.*)$/us', $str, $matches);
        return \mb_strtoupper($matches[1]) . $matches[2];
    }


    /**
     * Strips whitespace (or other UTF-8 characters) from the beginning and
     * end of a string. This is a UTF8-aware version of [trim](http://php.net/trim).
     *
     * @param string $str input string
     * @param string|null $charlist string of characters to remove
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    public static function trim(string $str, string $charlist = null): string
    {
        if ($charlist === null) {
            return \trim($str);
        }

        return self::ltrim(self::rtrim($str, $charlist), $charlist);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the beginning of
     * a string. This is a UTF8-aware version of [ltrim](http://php.net/ltrim).
     *
     * @param string $str input string
     * @param string|null $charlist string of characters to remove
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    public static function ltrim(string $str, string $charlist = null): string
    {
        if ($charlist === null) {
            return \ltrim($str);
        }

        if (self::isAscii($charlist)) {
            return \ltrim($str, $charlist);
        }

        $charlist = \preg_replace('#[-\[\]:\\\\^/]#', '\\\\$0', $charlist);

        return \preg_replace('/^[' . $charlist . ']+/u', '', $str);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the end of a string.
     * This is a UTF8-aware version of [rtrim](http://php.net/rtrim).
     *
     * @param string $str input string
     * @param string|null $charlist string of characters to remove
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    public static function rtrim(string $str, string $charlist = null): string
    {
        if ($charlist === null) {
            return \rtrim($str);
        }

        if (self::isAscii($charlist)) {
            return \rtrim($str, $charlist);
        }

        $charlist = \preg_replace('#[-\[\]:\\\\^/]#', '\\\\$0', $charlist);

        return \preg_replace('/[' . $charlist . ']++$/uD', '', $str);
    }


    public static function ruTranslit(string $str): string
    {
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

        return \str_replace(
            \array_keys($trans_table),
            \array_values($trans_table),
            $str
        );
    }
}
