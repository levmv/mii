<?php declare(strict_types=1);

namespace mii\util;

class URL
{
    public const CURRENT = 1;
    public const ACTIVE = 2;

    private static bool $inited = false;
    private static string $uri = '';
    private static array $uri_pieces = [];
    private static int $uri_size;

    public static function status(string $link): int
    {
        if (!self::$inited) {
            self::$uri = \trim(\Mii::$app->request->uri(), '/');
            self::$uri_pieces = \explode('/', self::$uri);
            self::$uri_size = \strlen(self::$uri);
        }

        $link = \trim($link, '/');
        $link_size = \strlen($link);

        if ($link_size > self::$uri_size) {
            return 0;
        }

        // Exact match
        if (self::$uri === $link) {
            return self::CURRENT;
        }

        // Checks if it is part of active path
        $link_pieces = \explode('/', $link);

        for ($i = 0, $iMax = \count($link_pieces); $i < $iMax; $i++) {
            if ((isset(self::$uri_pieces[$i]) && self::$uri_pieces[$i] !== $link_pieces[$i])
                || empty(self::$uri_pieces[$i])) {
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
     * @return  string
     */
    public static function base($protocol = null): string
    {
        $base = \Mii::$app->base_url ?? '/';
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
     * @param mixed  $protocol Protocol string or true
     * @return  string
     */
    public static function site(string $uri = '', $protocol = null): string
    {
        // Chop off possible scheme, host, port, user and pass parts
        $path = \preg_replace('~^[-a-z0-9+.]++://[^/]++/?~', '', \trim($uri, '/'));

        if (\preg_match('/[^\x00-\x7F]/S', $path)) {
            // Encode all non-ASCII characters, as per RFC 1738
            $path = \preg_replace_callback('~([^/]+)~', '\mii\util\URL::_rawurlencode_callback', $path);
        }

        // Concat the URL
        return self::base($protocol) . $path;
    }

    /**
     * Callback used for encoding all non-ASCII characters, as per RFC 1738
     * Used by URL::site()
     *
     * @param array $matches Array of matches from preg_replace_callback()
     * @return string          Encoded string
     */
    protected static function _rawurlencode_callback($matches)
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
     * Typically you would use this when you are sorting query results,
     * or something similar.
     *
     * [!!] Parameters with a NULL value are left out.
     *
     * @param array   $params Array of GET parameters
     * @param boolean $use_get Include current request GET parameters
     * @return  string
     */
    public static function query(array $params = null, $use_get = null)
    {
        if ($use_get) {
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
        $query = \http_build_query($params, '', '&');

        // Don't prepend '?' to an empty string
        return ($query === '') ? '' : ('?' . $query);
    }


    public static function current(array $params = null): string
    {
        return static::site(\Mii::$app->request->uri()) . static::query($params, true);
    }


    public static function back_url(string $default = null): string
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
