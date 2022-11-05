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


    private static ?int $today = null;
    private static ?int $year = null;

    public static function nice(int $timestamp, bool $withTime = true): string
    {
        static::$today ??= \mktime(0, 0, 0);
        static::$year ??= \mktime(0, 0, 0, 1, 1);

        if ($timestamp > static::$today) {
            $result = 'сегодня';
            if ($withTime) {
                $result .= ' в ' . \date('H:i', $timestamp);
            }
            return $result;
        }

        $format = $timestamp > static::$year
            ? 'd MMMM'
            : 'd MMMM YYYY';

        if($withTime) {
            $format .= " 'в' H:mm";
        }

        return self::intl($format)->format($timestamp);
    }


    /**
     * @deprecated
     */
    public static function fuzzy(int $timestamp, int $local_timestamp = null): string
    {
        $local_timestamp = $local_timestamp ?? \time();

        // Determine the difference in seconds
        $offset = \abs($local_timestamp - $timestamp);

        if ($timestamp > $local_timestamp) {
            // this is future
            return static::fuzzyFuture($timestamp, $offset);
        }

        if ($offset < 61) {
            return 'только что';
        } elseif ($offset < 60 * 55) {
            $minutes = (int) \round($offset / 60);
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
            $minutes = (int) \round($offset / 60);
            return "через $minutes " . Text::decl($minutes, ['минуту', 'минуты', 'минут']);
        } elseif ($offset < 60 * 65) {
            return 'через час';
        }

        static::$today ??= \mktime(0, 0, 0);

        if ($offset < self::DAY) {
            $tomorrow = \mktime(24, 0, 1);
            return ($timestamp < $tomorrow ? 'сегодня в ' : 'завтра в ') . \date('H:i', $timestamp);
        }

        return \strftime('%e %B в %k:%M', $timestamp);
    }


    /**
     * @deprecated
     */
    public static function formated($timestamp, $local_timestamp = null): string
    {
        return static::fuzzy($timestamp, $local_timestamp);
    }

    public static function dayOfWeek($date = null, $ucfirst = true) : string
    {
        $days = [
            'воскресенье', 'понедельник',
            'вторник', 'среда',
            'четверг', 'пятница', 'суббота',
        ];

        $uf_days = [
            'Воскресенье', 'Понедельник',
            'Вторник', 'Среда',
            'Четверг', 'Пятница', 'Суббота',
        ];


        return ($ucfirst) ? $uf_days[\date('w', $date)] : $days[\date('w', $date)];
    }

    public static function month(int $date = null, bool $genitive = true): string
    {
        static $table = ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
        static $table_g = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

        $n = \date('n', $date) - 1;

        if ($genitive) {
            return $table_g[$n];
        }

        return $table[$n];
    }

    /**
     * Primitive temporary replacement for strftime.
     * Support only simple patterns that have real usage in our projects
     *
     * Strongly not recommended to use in new code!
     *
     */
    static function strftime(string $format, int $timestamp = null): string
    {

        $pattern = str_replace(
            ['%a', '%d', '%e', '%B', '%m', '%G', '%Y', '%H', '%k', '%M', '%T', '%F'],
            ['ccc', 'dd', 'd', 'MMMM', 'LL', 'yyyy', 'yyyy', 'H', 'H', 'mm', 'HH:mm:ss', 'yyyy-MM-dd'], $format);

        $formatter = (new \IntlDateFormatter(\Mii::$app->locale, 0, 0, null, null, $pattern));

        $result = $formatter->format($timestamp ?? new \DateTime());

        if ($result === false) {
            throw new \InvalidArgumentException($formatter->getErrorMessage());
        }

        return $result;
    }


    private static array $formatters = [];

    public static function intl(string $pattern = ''): \IntlDateFormatter {

        if(!isset(static::$formatters[$pattern])) {
            static::$formatters[$pattern] = new \IntlDateFormatter(\Mii::$app->locale, 0, 0, null, null, $pattern);
        }

        return static::$formatters[$pattern];
    }
}
