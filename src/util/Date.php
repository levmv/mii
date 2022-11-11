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

    protected static ?int $today = null;
    protected static ?int $thisYear = null;

    public static function nice(int $timestamp, string $format = '%D в %T'): string
    {
        return strtr($format, [
            '%D' => self::niceDate($timestamp),
            '%T' => \date('G:i', $timestamp)
        ]);
    }

    /**
     * Hint: Intl is more than two times slower than naive-straight-php-date-months-array-way. So, if you need
     * to format some hundred thousand dates very fast and do not care about multiple locales - maybe use old way?
     *
     * @param int $timestamp
     * @return string
     */
    public static function niceDate(int $timestamp): string
    {
        static::$today ??= \mktime(0, 0, 0);

        if ($timestamp > static::$today) {
            return 'сегодня';
        }

        static::$thisYear ??= \mktime(0, 0, 0, 1, 1);

        return self::intl($timestamp > static::$thisYear ? 'd MMMM' : 'd MMMM YYYY')->format($timestamp);
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
