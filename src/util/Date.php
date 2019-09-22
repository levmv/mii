<?php

/**
 * Ohanzee Components by Kohana
 *
 * @package    Ohanzee
 * @author     Kohana Team <team@kohanaframework.org>
 * @copyright  2007-2014 Kohana Team
 * @link       http://ohanzee.org/
 * @license    http://ohanzee.org/license
 * @version    0.1.0
 *
 * BSD 2-CLAUSE LICENSE
 *
 * This license is a legal agreement between you and the Kohana Team for the use
 * of Kohana Framework and Ohanzee Components (the "Software"). By obtaining the
 * Software you agree to comply with the terms and conditions of this license.
 *
 * Copyright (c) 2007-2014 Kohana Team
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1) Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * 2) Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
 * INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
 * OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace mii\util;

class Date
{
    // Second amounts for various time increments
    const YEAR = 31556926;
    const MONTH = 2629744;
    const WEEK = 604800;
    const DAY = 86400;
    const HOUR = 3600;
    const MINUTE = 60;

    // Available formats for Date::months()
    const MONTHS_LONG = '%B';
    const MONTHS_SHORT = '%b';

    /**
     * Default timestamp format for formatted_time
     * @var  string
     */
    public static $timestamp_format = 'Y-m-d H:i:s';

    /**
     * Timezone for formatted_time
     * @link http://uk2.php.net/manual/en/timezones.php
     * @var  string
     */
    public static $timezone;

    /**
     * Returns the offset (in seconds) between two time zones. Use this to
     * display dates to users in different time zones.
     *
     *     $seconds = Date::offset('America/Chicago', 'GMT');
     *
     * [!!] A list of time zones that PHP supports can be found at
     * <http://php.net/timezones>.
     *
     * @param string $remote timezone that to find the offset of
     * @param string $local timezone used as the baseline
     * @param mixed $now UNIX timestamp or date string
     *
     * @return integer
     */
    public static function offset($remote, $local = null, $now = null) {
        if ($local === null) {
            // Use the default timezone
            $local = date_default_timezone_get();
        }

        if (is_int($now)) {
            // Convert the timestamp into a string
            $now = date(\DateTime::RFC2822, $now);
        }

        // Create timezone objects
        $zone_remote = new \DateTimeZone($remote);
        $zone_local = new \DateTimeZone($local);

        // Create date objects from timezones
        $time_remote = new \DateTime($now, $zone_remote);
        $time_local = new \DateTime($now, $zone_local);

        // Find the offset
        $offset = $zone_remote->getOffset($time_remote) - $zone_local->getOffset($time_local);

        return $offset;
    }


    /**
     * Returns time difference between two timestamps, in human readable format.
     * If the second timestamp is not given, the current time will be used.
     * Also consider using [Date::fuzzy_span] when displaying a span.
     *
     *     $span = Date::span(60, 182, 'minutes,seconds'); // array('minutes' => 2, 'seconds' => 2)
     *     $span = Date::span(60, 182, 'minutes'); // 2
     *
     * @param   integer $remote timestamp to find the span of
     * @param   integer $local timestamp to use as the baseline
     * @param   string $output formatting string
     * @return  string   when only a single output is requested
     * @return  array    associative list of all outputs requested
     */
    public static function span($remote, $local = null, $output = 'years,months,weeks,days,hours,minutes,seconds') {
        // Normalize output
        $output = trim(strtolower((string)$output));

        if (!$output) {
            // Invalid output
            return false;
        }

        // Array with the output formats
        $output = preg_split('/[^a-z]+/', $output);

        // Convert the list of outputs to an associative array
        $output = array_combine($output, array_fill(0, count($output), 0));

        // Make the output values into keys
        extract(array_flip($output), EXTR_SKIP);

        if ($local === null) {
            // Calculate the span from the current time
            $local = time();
        }

        // Calculate timespan (seconds)
        $timespan = abs($remote - $local);

        if (isset($output['years'])) {
            $timespan -= static::YEAR * ($output['years'] = (int)floor($timespan / static::YEAR));
        }

        if (isset($output['months'])) {
            $timespan -= static::MONTH * ($output['months'] = (int)floor($timespan / static::MONTH));
        }

        if (isset($output['weeks'])) {
            $timespan -= static::WEEK * ($output['weeks'] = (int)floor($timespan / static::WEEK));
        }

        if (isset($output['days'])) {
            $timespan -= static::DAY * ($output['days'] = (int)floor($timespan / static::DAY));
        }

        if (isset($output['hours'])) {
            $timespan -= static::HOUR * ($output['hours'] = (int)floor($timespan / static::HOUR));
        }

        if (isset($output['minutes'])) {
            $timespan -= static::MINUTE * ($output['minutes'] = (int)floor($timespan / static::MINUTE));
        }

        // Seconds ago, 1
        if (isset($output['seconds'])) {
            $output['seconds'] = $timespan;
        }

        if (count($output) === 1) {
            // Only a single output was requested, return it
            return array_pop($output);
        }

        // Return array
        return $output;
    }

    /**
     * Returns the difference between a time and now in a "fuzzy" way.
     * Displaying a fuzzy time instead of a date is usually faster to read and understand.
     *
     *     $span = Date::fuzzy_span(time() - 10); // "moments ago"
     *     $span = Date::fuzzy_span(time() + 20); // "in moments"
     *
     * A second parameter is available to manually set the "local" timestamp,
     * however this parameter shouldn't be needed in normal usage and is only
     * included for unit tests
     *
     * @param   integer $timestamp "remote" timestamp
     * @param   integer $local_timestamp "local" timestamp, defaults to time()
     * @return  string
     */
    public static function fuzzySpan($timestamp, $local_timestamp = null) {
        $local_timestamp = ($local_timestamp === null) ? time() : (int)$local_timestamp;

        // Determine the difference in seconds
        $offset = abs($local_timestamp - $timestamp);

        if ($offset <= static::MINUTE) {
            $span = 'moments';
        } elseif ($offset < (static::MINUTE * 20)) {
            $span = 'a few minutes';
        } elseif ($offset < static::HOUR) {
            $span = 'less than an hour';
        } elseif ($offset < (static::HOUR * 4)) {
            $span = 'a couple of hours';
        } elseif ($offset < static::DAY) {
            $span = 'less than a day';
        } elseif ($offset < (static::DAY * 2)) {
            $span = 'about a day';
        } elseif ($offset < (static::DAY * 4)) {
            $span = 'a couple of days';
        } elseif ($offset < static::WEEK) {
            $span = 'less than a week';
        } elseif ($offset < (static::WEEK * 2)) {
            $span = 'about a week';
        } elseif ($offset < static::MONTH) {
            $span = 'less than a month';
        } elseif ($offset < (static::MONTH * 2)) {
            $span = 'about a month';
        } elseif ($offset < (static::MONTH * 4)) {
            $span = 'a couple of months';
        } elseif ($offset < static::YEAR) {
            $span = 'less than a year';
        } elseif ($offset < (static::YEAR * 2)) {
            $span = 'about a year';
        } elseif ($offset < (static::YEAR * 4)) {
            $span = 'a couple of years';
        } elseif ($offset < (static::YEAR * 8)) {
            $span = 'a few years';
        } elseif ($offset < (static::YEAR * 12)) {
            $span = 'about a decade';
        } elseif ($offset < (static::YEAR * 24)) {
            $span = 'a couple of decades';
        } elseif ($offset < (static::YEAR * 64)) {
            $span = 'several decades';
        } else {
            $span = 'a long time';
        }

        if ($timestamp <= $local_timestamp) {
            // This is in the past
            return $span . ' ago';
        } else {
            // This in the future
            return 'in ' . $span;
        }
    }


    /**
     * Returns a date/time string with the specified timestamp format
     *
     *     $time = Date::formatted_time('5 minutes ago');
     *
     * @link    http://www.php.net/manual/\DateTime.construct
     * @param   string $\DateTime_str       \DateTime string
     * @param   string $timestamp_format timestamp format
     * @param   string $timezone timezone identifier
     * @return  string
     */
    public static function formattedTime($DateTime_str = 'now', $timestamp_format = null, $timezone = null) {
        if (!$timestamp_format) {
            $timestamp_format = static::$timestamp_format;
        }

        if (!$timezone) {
            $timezone = static::$timezone;
        }

        $tz = new \DateTimeZone($timezone);
        $time = new \DateTime($DateTime_str, $tz);

        if ($time->getTimeZone()->getName() !== $tz->getName()) {
            $time->setTimeZone($tz);
        }

        return $time->format($timestamp_format);
    }


    public static $ru_months = array('September' => 'сентября', 'November' => 'ноября', 'October' => 'октября', 'December' => 'декабря',
        'January' => 'января', 'February' => 'февраля', 'March' => 'марта', 'April' => 'апреля',
        'May' => 'мая', 'June' => 'июня', 'July' => 'июля', 'August' => 'августа');


    static function strftime_rus($format, $date = FALSE) {
        // Работает точно так же, как и strftime(),
        // только в строке формата может принимать
        // дополнительный аргумент %B2, который будет заменен
        // на русское название месяца в родительном падеже.

        // В остальном правила для $format такие же, как и для strftime().
        // (в связи с этим рекомендуется настроить выполнение скрипта
        // с помощью setlocale: http://php.net/setlocale)

        // Второй аргумент можно передавать как в виде временной метки
        // так и в виде строки типа 2010-05-16 23:48:20
        // функция сама определит, в каком виде передана дата,
        // и проведет преобразование.
        // Если второй аргумент не указан,
        // функция будет работать с текущим временем.

        if (!$date)
            $timestamp = time();

        elseif (!is_numeric($date))
            $timestamp = strtotime($date);

        else
            $timestamp = $date;

        if (strpos($format, '%B2') === FALSE)
            return strftime($format, $timestamp);

        $month_number = date('n', $timestamp);

        switch ($month_number) {
            case 1:
                $rus = 'января';
                break;
            case 2:
                $rus = 'февраля';
                break;
            case 3:
                $rus = 'марта';
                break;
            case 4:
                $rus = 'апреля';
                break;
            case 5:
                $rus = 'мая';
                break;
            case 6:
                $rus = 'июня';
                break;
            case 7:
                $rus = 'июля';
                break;
            case 8:
                $rus = 'августа';
                break;
            case 9:
                $rus = 'сентября';
                break;
            case 10:
                $rus = 'октября';
                break;
            case 11:
                $rus = 'ноября';
                break;
            case 12:
                $rus = 'декабря';
                break;
        }

        $rusformat = str_replace('%B2', $rus, $format);

        return strftime($rusformat, $timestamp);
    }

    public static function formated($timestamp, $local_timestamp = NULL) {
        $local_timestamp = ($local_timestamp === NULL) ? time() : (int)$local_timestamp;

        // Determine the difference in seconds
        $offset = abs($local_timestamp - $timestamp);


        if ($offset <= Date::MINUTE) {
            $span = 'только что';
        } elseif ($offset < Date::HOUR) {
            $minutes = round($offset / 60);

            $span = $minutes . ' ' . Text::decl($minutes, array('минуту', 'минуты', 'минут')) . ' назад';
        } elseif ($offset == Date::HOUR) {
            $span = 'час назад';
        } elseif ($offset < (Date::HOUR * 2)) {
            $span = 'два часа назад';
        } elseif ($offset < Date::DAY) {
            if (date('d', $timestamp) != date('d'))
                $span = 'вчера в ' . date('H:i', $timestamp);
            else
                $span = 'сегодня в ' . date('H:i', $timestamp);
        } elseif ($offset > Date::DAY) {
            if (date('Y', $timestamp) != date('Y'))
                $span = Date::strftime_rus('%e %B2 %Y', $timestamp) . ' в ' . date('H:i', $timestamp);
            else
                $span = Date::strftime_rus('%e %B2', $timestamp) . ' в ' . date('H:i', $timestamp);
        }


        if ($timestamp <= $local_timestamp) {
            // This is in the past
            return $span;
        } else {
            // This in the future
            return 'в ' . $span;
        }
    }

    public static function day_of_week($date = null, $ucfirst = true) {
        $days = [
            'воскресенье', 'понедельник',
            'вторник', 'среда',
            'четверг', 'пятница', 'суббота'
        ];

        $uf_days = [
            'Воскресенье', 'Понедельник',
            'Вторник', 'Среда',
            'Четверг', 'Пятница', 'Суббота'
        ];


        return ($ucfirst) ? $uf_days[date('w', $date)] : $days[date('w', $date)];
    }

    public static function month($date = null, $genitive = true) {
        static $trans_table = ['September' => 'сентябрь', 'November' => 'ноябрь', 'October' => 'октябрь', 'December' => 'декабрь',
            'January' => 'январь', 'February' => 'февраль', 'March' => 'март', 'April' => 'апрель',
            'May' => 'май', 'June' => 'июнь', 'July' => 'июль', 'August' => 'август'];


        static $trans_table_g = ['September' => 'сентября', 'November' => 'ноября', 'October' => 'октября', 'December' => 'декабря',
            'January' => 'января', 'February' => 'февраля', 'March' => 'марта', 'April' => 'апреля',
            'May' => 'мая', 'June' => 'июня', 'July' => 'июля', 'August' => 'августа'];

        $name = date('F', $date);

        if ($genitive) {
            return isset($trans_table_g[$name]) ? $trans_table_g[$name] : $name;
        } else {
            return isset($trans_table[$name]) ? $trans_table[$name] : $name;
        }
    }
}