<?php /** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
declare(strict_types=1);

namespace mii\util;

use Mii;
use mii\core\Exception;

/**
 * Provides simple benchmarking and profiling.
 *
 * @copyright  (c) 2009-2012 Kohana Team
 */
class Profiler
{
    private const GROUP = 0;
    private const NAME = 1;
    private const START_TIME = 2;
    private const START_MEMORY = 3;
    private const STOP_TIME = 4;
    private const STOP_MEMORY = 5;

    /**
     * @var  integer   maximium number of application stats to keep
     */
    public static int $rollover = 1000;

    /**
     * @var  array  collected benchmarks
     */
    protected static array $_marks = [];

    /**
     * Starts a new benchmark and returns a unique token. The returned token
     * _must_ be used when stopping the benchmark.
     *
     *     $token = Profiler::start('test', 'profiler');
     *
     * @param string $group group name
     * @param string $name benchmark name
     */
    public static function start(string $group, string $name): string
    {
        static $counter = 0;

        // Create a unique token based on the counter
        $token = 'kp/' . \base_convert((string)$counter++, 10, 32);

        Profiler::$_marks[$token] = [
            self::GROUP => \strtolower($group),
            self::NAME => $name,

            // Start the benchmark
            self::START_TIME => \hrtime(true) / 1e9,
            self::START_MEMORY => \memory_get_usage(),

            // Set the stop keys without values
            self::STOP_TIME => false,
            self::STOP_MEMORY => false,
        ];

        return $token;
    }

    /**
     * Stops a benchmark.
     *
     *     Profiler::stop($token);
     *
     * @param string $token
     */
    public static function stop(string $token): bool
    {
        // Stop the benchmark
        self::$_marks[$token][self::STOP_TIME] = \hrtime(true) / 1e9;
        self::$_marks[$token][self::STOP_MEMORY] = \memory_get_usage();
        return true;
    }

    /**
     * Deletes a benchmark. If an error occurs during the benchmark, it is
     * recommended to delete the benchmark to prevent statistics from being
     * adversely affected.
     *
     *     Profiler::delete($token);
     *
     * @param string $token
     */
    public static function delete(string $token): bool
    {
        // Remove the benchmark
        unset(self::$_marks[$token]);
        return true;
    }

    /**
     * Returns all the benchmark tokens by group and name as an array.
     *
     *     $groups = Profiler::groups();
     */
    public static function groups(): array
    {
        $groups = [];

        foreach (self::$_marks as $token => $mark) {
            // Sort the tokens by the group and name
            $groups[$mark[self::GROUP]][$mark[self::NAME]][] = $token;
        }

        return $groups;
    }

    /**
     * Gets the min, max, average and total of a set of tokens as an array.
     *
     *     $stats = Profiler::stats($tokens);
     *
     * @param array $tokens profiler tokens
     * @return  array   min, max, average, total
     */
    public static function stats(array $tokens): array
    {
        // Min and max are unknown by default
        $min = $max = [
            'time' => null,
            'memory' => null,
        ];

        // Total values are always integers
        $total = [
            'time' => 0,
            'memory' => 0,
        ];

        foreach ($tokens as $token) {
            // Get the total time and memory for this benchmark
            [$time, $memory] = self::total($token);

            if ($max['time'] === null or $time > $max['time']) {
                // Set the maximum time
                $max['time'] = $time;
            }

            if ($min['time'] === null or $time < $min['time']) {
                // Set the minimum time
                $min['time'] = $time;
            }

            // Increase the total time
            $total['time'] += $time;

            if ($max['memory'] === null or $memory > $max['memory']) {
                // Set the maximum memory
                $max['memory'] = $memory;
            }

            if ($min['memory'] === null or $memory < $min['memory']) {
                // Set the minimum memory
                $min['memory'] = $memory;
            }

            // Increase the total memory
            $total['memory'] += $memory;
        }

        // Determine the number of tokens
        $count = \count($tokens);

        // Determine the averages
        $average = [
            'time' => $total['time'] / $count,
            'memory' => $total['memory'] / $count,
        ];

        return [
            'min' => $min,
            'max' => $max,
            'total' => $total,
            'average' => $average, ];
    }

    /**
     * Gets the min, max, average and total of profiler groups as an array.
     *
     *     $stats = Profiler::group_stats('test');
     *
     * @param string|array|null $groups single group name string, or array with group names; all groups by default
     * @return  array   min, max, average, total
     */
    public static function groupStats(string|array $groups = null): array
    {
        // Which groups do we need to calculate stats for?
        $groups = ($groups === null)
            ? self::groups()
            : \array_intersect_key(self::groups(), \array_flip((array) $groups));

        // All statistics
        $stats = [];

        foreach ($groups as $group => $names) {
            foreach ($names as $name => $tokens) {
                // Store the stats for each subgroup.
                // We only need the values for "total".
                $_stats = Profiler::stats($tokens);
                $stats[$group][$name] = $_stats['total'];
            }
        }

        // Group stats
        $groups = [];

        foreach ($stats as $group => $names) {
            // Min and max are unknown by default
            $groups[$group]['min'] = $groups[$group]['max'] = [
                'time' => null,
                'memory' => null, ];

            // Total values are always integers
            $groups[$group]['total'] = [
                'time' => 0,
                'memory' => 0, ];

            foreach ($names as $total) {
                if (!isset($groups[$group]['min']['time']) or $groups[$group]['min']['time'] > $total['time']) {
                    // Set the minimum time
                    $groups[$group]['min']['time'] = $total['time'];
                }
                if (!isset($groups[$group]['min']['memory']) or $groups[$group]['min']['memory'] > $total['memory']) {
                    // Set the minimum memory
                    $groups[$group]['min']['memory'] = $total['memory'];
                }

                if (!isset($groups[$group]['max']['time']) or $groups[$group]['max']['time'] < $total['time']) {
                    // Set the maximum time
                    $groups[$group]['max']['time'] = $total['time'];
                }
                if (!isset($groups[$group]['max']['memory']) or $groups[$group]['max']['memory'] < $total['memory']) {
                    // Set the maximum memory
                    $groups[$group]['max']['memory'] = $total['memory'];
                }

                // Increase the total time and memory
                $groups[$group]['total']['time'] += $total['time'];
                $groups[$group]['total']['memory'] += $total['memory'];
            }

            // Determine the number of names (subgroups)
            $count = \count($names);

            // Determine the averages
            $groups[$group]['average']['time'] = $groups[$group]['total']['time'] / $count;
            $groups[$group]['average']['memory'] = $groups[$group]['total']['memory'] / $count;
        }

        return $groups;
    }

    /**
     * Gets the total execution time and memory usage of a benchmark as a list.
     *
     *     list($time, $memory) = Profiler::total($token);
     *
     * @param string $token
     * @return  array   execution time, memory
     */
    public static function total(string $token): array
    {
        // Import the benchmark data
        $mark = Profiler::$_marks[$token];

        if ($mark[self::STOP_TIME] === false) {
            // The benchmark has not been stopped yet
            $mark[self::STOP_TIME] = \hrtime(true) / 1e9;
            $mark[self::STOP_MEMORY] = \memory_get_usage();
        }

        return [
            // Total time in seconds
            $mark[self::STOP_TIME] - $mark[self::START_TIME],

            // Amount of memory in bytes
            $mark[self::STOP_MEMORY] - $mark[self::START_MEMORY],
        ];
    }

    /**
     * Gets the total application run time and memory usage. Caches the result
     * so that it can be compared between requests.
     *
     *     list($time, $memory) = Profiler::application();
     *
     * @return  array  execution time, memory
     * @throws Exception
     */
    public static function application(): array
    {
        // Load the stats from cache, which is valid for 12 hours

        $stats = Mii::$app->has('cache')
            ? Mii::$app->cache->get('profiler_app_stats')
            : null;

        if (!\is_array($stats) or $stats['count'] > Profiler::$rollover) {
            // Initialize the stats array
            $stats = [
                'min' => [
                    'time' => null,
                    'memory' => null,
                ],
                'max' => [
                    'time' => null,
                    'memory' => null,
                ],
                'total' => [
                    'time' => null,
                    'memory' => null,
                ],
                'count' => 0,
            ];
        }
        if (!\defined('MII_START_TIME')) {
            throw new Exception('Probably you disabled asserts. Please set "zend.assertions = 1" in php.ini');
        }

        // Get the application run time
        $time = \hrtime(true) / 1e9 - MII_START_TIME;

        // Get the total memory usage
        $memory = \memory_get_usage() - MII_START_MEMORY;

        // Calculate max time
        if ($stats['max']['time'] === null or $time > $stats['max']['time']) {
            $stats['max']['time'] = $time;
        }

        // Calculate min time
        if ($stats['min']['time'] === null or $time < $stats['min']['time']) {
            $stats['min']['time'] = $time;
        }

        // Add to total time
        $stats['total']['time'] += $time;

        // Calculate max memory
        if ($stats['max']['memory'] === null or $memory > $stats['max']['memory']) {
            $stats['max']['memory'] = $memory;
        }

        // Calculate min memory
        if ($stats['min']['memory'] === null or $memory < $stats['min']['memory']) {
            $stats['min']['memory'] = $memory;
        }

        // Add to total memory
        $stats['total']['memory'] += $memory;

        // Another mark has been added to the stats
        $stats['count']++;

        // Determine the averages
        $stats['average'] = [
            'time' => $stats['total']['time'] / $stats['count'],
            'memory' => $stats['total']['memory'] / $stats['count'], ];

        // Cache the new stats
        if (Mii::$app->has('cache')) {
            Mii::$app->cache->set('profiler_app_stats', $stats, 3600 * 12);
        }

        // Set the current application execution time and memory
        // Do NOT cache these, they are specific to the current request only
        $stats['current']['time'] = $time;
        $stats['current']['memory'] = $memory;

        // Return the total application run time and memory usage
        return $stats;
    }


    public static function show()
    {
        include \dirname(__DIR__) . '/util/Profiler/view.php';
    }
}
