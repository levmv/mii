<?php declare(strict_types=1);

namespace mii\util;

class Url
{
    final public const CURRENT = 1;
    final public const ACTIVE = 2;

    private static bool $inited = false;
    private static string $uri = '';
    private static array $uriPieces = [];
    private static int $uriSize;

    public static function status(string $link): int
    {
        if (!self::$inited) {
            self::$uri = \trim(\Mii::$app->request->uri(), '/');
            self::$uriPieces = \explode('/', self::$uri);
            self::$uriSize = \strlen(self::$uri);
        }

        $link = \trim($link, '/');

        if (\strlen($link) > self::$uriSize) {
            return 0;
        }

        // Exact match
        if (self::$uri === $link) {
            return self::CURRENT;
        }

        // Checks if it is part of active path
        $parts = \explode('/', $link);

        for ($i = 0, $iMax = \count($parts); $i < $iMax; $i++) {
            if ((isset(self::$uriPieces[$i]) && self::$uriPieces[$i] !== $parts[$i]) || empty(self::$uriPieces[$i])) {
                return 0;
            }
        }
        return self::ACTIVE;
    }

    public static function isCurrent(string $link): bool
    {
        return self::status($link) === self::CURRENT;
    }

    public static function isActive(string $link): bool
    {
        return self::status($link) === self::ACTIVE;
    }


    /**
     * Gets the base URL to the application.
     * To specify a protocol, provide the protocol as a string or request object.
     * If a protocol is used, a complete URL will be generated using the
     * `$_SERVER['HTTP_HOST']` variable.
     *
     *     // Absolute URL path with no host or protocol
     *     echo URL::base();
     *
     *     // Absolute URL path with host, https protocol
     *     echo URL::base('https');
     *
     *     // Absolute URL path with '//'
     *     echo URL::base('//');
     *
     * @param mixed $protocol Protocol string or boolean
     */
    public static function base(mixed $protocol = null): string
    {
        $base = \Mii::$app->base_url ?? '';
        if ($protocol === null) {
            return $base;
        }

        if ($protocol === true) {
            return \Mii::$app->request->getHostname() . $base;
        }

        if ($protocol !== '//') {
            $protocol .= '://';
        }

        $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];

        return $protocol . $domain . $base;
    }

    /**
     * Fetches an absolute site URL based on a URI segment.
     *
     *     echo URL::site('foo/bar');
     *
     * @param string $uri Site URI to convert
     * @param mixed|null $protocol Protocol string or true
     */
    public static function site(string $uri = '', mixed $protocol = null): string
    {
        // Chop off possible scheme, host, port, user and pass parts
        $path = \preg_replace('~^[-a-z0-9+.]++://[^/]++/?~', '', \trim($uri, '/'));

        if (\preg_match('/[^\x00-\x7F]/S', $path)) {
            // Encode all non-ASCII characters, as per RFC 1738
            $path = \preg_replace_callback('~([^/]+)~', '\mii\util\Url::_rawurlencode_callback', $path);
        }

        // Concat the URL
        return self::base($protocol) . '/' . $path;
    }

    /**
     * Callback used for encoding all non-ASCII characters, as per RFC 1738
     * Used by URL::site()
     *
     * @param array $matches Array of matches from preg_replace_callback()
     */
    protected static function _rawurlencode_callback(array $matches): string
    {
        return \rawurlencode($matches[0]);
    }

    /**
     * Merges the current GET parameters with an array of new or overloaded
     * parameters and returns the resulting query string.
     *
     *     // Returns "?sort=title&limit=10" combined with any existing GET values
     *     $query = URL::query(array('sort' => 'title', 'limit' => 10));
     *
     * Typically, you would use this when you are sorting query results,
     * or something similar.
     *
     * [!!] Parameters with a NULL value are left out.
     *
     * @param array|null $params Array of GET parameters
     * @param bool $useGet Include current request GET parameters
     */
    public static function query(array $params = null, bool $useGet = false): string
    {
        if ($useGet) {
            if ($params === null) {
                // Use only the current parameters
                $params = $_GET;
            } else {
                // Merge the current and new parameters
                $params = Arr::merge($_GET, $params);
            }
        }

        if (empty($params)) {
            // No query parameters
            return '';
        }

        // Note: http_build_query returns an empty string for a params array with only NULL values
        $query = \http_build_query($params);

        // Don't prepend '?' to an empty string
        return ($query === '') ? '' : ('?' . $query);
    }


    public static function current(array $params = null): string
    {
        return self::base(). \Mii::$app->request->uri() . static::query($params, true);
    }


    public static function back(string $default = null): string
    {
        if (isset($_GET['back_url'])) {
            return \urldecode($_GET['back_url']);
        }

        if ($default === null) {
            return self::current();
        }

        return $default;
    }
}
