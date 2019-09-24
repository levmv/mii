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
    const MONTH = 2629744;
    const WEEK = 604800;
    const DAY = 86400;
    const HOUR = 3600;
    const MINUTE = 60;


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