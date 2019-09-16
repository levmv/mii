<?php

namespace mii\util;


class Num
{

    /**
     * @var  array  Valid byte units => power of 2 that defines the unit's size
     */
    public static $byte_units = array
    (
        'B' => 0,
        'K' => 10,
        'Ki' => 10,
        'KB' => 10,
        'KiB' => 10,
        'M' => 20,
        'Mi' => 20,
        'MB' => 20,
        'MiB' => 20,
        'G' => 30,
        'Gi' => 30,
        'GB' => 30,
        'GiB' => 30,
        'T' => 40,
        'Ti' => 40,
        'TB' => 40,
        'TiB' => 40,
        'P' => 50,
        'Pi' => 50,
        'PB' => 50,
        'PiB' => 50,
        'E' => 60,
        'Ei' => 60,
        'EB' => 60,
        'EiB' => 60,
        'Z' => 70,
        'Zi' => 70,
        'ZB' => 70,
        'ZiB' => 70,
        'Y' => 80,
        'Yi' => 80,
        'YB' => 80,
        'YiB' => 80,
    );

    /**
     * Returns the English ordinal suffix (th, st, nd, etc) of a number.
     *
     *     echo 2, Num::ordinal(2);   // "2nd"
     *     echo 10, Num::ordinal(10); // "10th"
     *     echo 33, Num::ordinal(33); // "33rd"
     *
     * @param   integer $number
     * @return  string
     */
    public static function ordinal($number) {
        if ($number % 100 > 10 AND $number % 100 < 14) {
            return 'th';
        }

        switch ($number % 10) {
            case 1:
                return 'st';
            case 2:
                return 'nd';
            case 3:
                return 'rd';
            default:
                return 'th';
        }
    }

    /**
     * Locale-aware number and monetary formatting.
     *
     *     // In English, "1,200.05"
     *     // In Spanish, "1200,05"
     *     // In Portuguese, "1 200,05"
     *     echo Num::format(1200.05, 2);
     *
     *     // In English, "1,200.05"
     *     // In Spanish, "1.200,05"
     *     // In Portuguese, "1.200.05"
     *     echo Num::format(1200.05, 2, TRUE);
     *
     * @param   float $number number to format
     * @param   integer $places decimal places
     * @param   boolean $monetary monetary formatting?
     * @return  string
     * @since   3.0.2
     */
    public static function format($number, $places, $monetary = FALSE) {
        $info = localeconv();

        if ($monetary) {
            $decimal = $info['mon_decimal_point'];
            $thousands = $info['mon_thousands_sep'];
        } else {
            $decimal = $info['decimal_point'];
            $thousands = $info['thousands_sep'];
        }

        return number_format($number, $places, $decimal, $thousands);
    }


    /**
     * Converts a file size number to a byte value. File sizes are defined in
     * the format: SB, where S is the size (1, 8.5, 300, etc.) and B is the
     * byte unit (K, MiB, GB, etc.). All valid byte units are defined in
     * Num::$byte_units
     *
     *     echo Num::bytes('200K');  // 204800
     *     echo Num::bytes('5MiB');  // 5242880
     *     echo Num::bytes('1000');  // 1000
     *     echo Num::bytes('2.5GB'); // 2684354560
     *
     * @param   string $bytes file size in SB format
     * @return  float
     */
    public static function bytes($size) {
        // Prepare the size
        $size = trim((string)$size);

        // Construct an OR list of byte units for the regex
        $accepted = implode('|', array_keys(Num::$byte_units));

        // Construct the regex pattern for verifying the size format
        $pattern = '/^([0-9]+(?:\.[0-9]+)?)(' . $accepted . ')?$/Di';

        // Verify the size format and store the matching parts
        if (!preg_match($pattern, $size, $matches))
            throw new Kohana_Exception('The byte unit size, ":size", is improperly formatted.', array(
                ':size' => $size,
            ));

        // Find the float value of the size
        $size = (float)$matches[1];

        // Find the actual unit, assume B if no unit specified
        $unit = Arr::get($matches, 2, 'B');

        // Convert the size into bytes
        $bytes = $size * pow(2, Num::$byte_units[$unit]);

        return $bytes;
    }



    /**
     * Declination of number
     * @param $number
     * @param mixed $array
     * @return mixed
     */
    public static function decl($number, $array) {

        $cases = array(2, 0, 1, 1, 1, 2);

        if ($number % 100 > 4 AND $number % 100 < 20) {
            return $array[2];
        } else {
            return $array[$cases[min($number % 10, 5)]];
        }
    }

}
