<?php declare(strict_types=1);


namespace mii\util;

class Date
{
    // Second amounts for various time increments
    public const MONTH = 2629744;
    public const WEEK = 604800;
    public const DAY = 86400;
    public const HOUR = 3600;
    public const MINUTE = 60;


    static private ?int $today = null;
    static private ?int $tomorrow = null;
    static private ?int $year = null;

    public static function nice(int $timestamp, bool $with_time = true): string
    {
        static::$today ??= \mktime(0, 0, 0);
        static::$year ??= \mktime(0, 0, 0, 0, 0);

        if ($timestamp > static::$today) {
            $pattern = 'сегодня';
        } elseif ($timestamp > static::$year) {
            $pattern = '%e %B';
        } else {
            $pattern = '%e %B %Y';
        }

        if ($with_time) {
            $pattern .= ' в %k:%M';
        }

        return \strftime($pattern, $timestamp);
    }


    public static function fuzzy(int $timestamp, int $local_timestamp = NULL): string
    {
        $local_timestamp = $local_timestamp ?? time();

        // Determine the difference in seconds
        $offset = \abs($local_timestamp - $timestamp);

        if ($timestamp > $local_timestamp) {
            // this is future
            return static::fuzzyFuture($timestamp, $offset);
        }

        if ($offset < 61) {
            return 'только что';
        } elseif ($offset < 60 * 55) {
            $minutes = round($offset / 60);
            return $minutes . ' ' . Text::decl($minutes, ['минуту', 'минуты', 'минут']) . ' назад';
        } elseif ($offset < 3600 + 60 * 15) {
            return 'час назад';
        } elseif ($offset < (3600 * 2)) {
            $span = 'два часа назад';
        } else {
            $span = self::nice($timestamp);
        }

        return $span;
    }

    private static function fuzzyFuture(int $timestamp, int $offset): string
    {
        if ($offset < 60) {
            return 'через мгновение';
        } elseif ($offset < 60 * 55) {
            $minutes = round($offset / 60);
            return "через $minutes " . Text::decl($minutes, ['минуту', 'минуты', 'минут']);
        } elseif ($offset < 60 * 65) {
            return "через час";
        }

        static::$today ??= \mktime(0, 0, 0);

        if ($offset < self::DAY) {
            $tomorrow = mktime(24, 0, 1);
            return ($timestamp < $tomorrow ? 'сегодня в ' : 'завтра в ') . date('H:i', $timestamp);
        }

        return \strftime('%e %B в %k:%M', $timestamp);
    }


    /**
     * @deprecated
     */
    public static function formated($timestamp, $local_timestamp = NULL)
    {
        return static::fuzzy($timestamp, $local_timestamp);
    }

    public static function dayOfWeek($date = null, $ucfirst = true) : string
    {
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

    public static function month(int $date = null, bool $genitive = true): string
    {
        static $table = ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'ноябрь', 'октябрь', 'декабрь'];
        static $table_g = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'ноября', 'октября', 'декабря'];

        $n = \date('n', $date) - 1;

        if ($genitive) {
            return $table_g[$n];
        }

        return $table[$n];
    }
}
