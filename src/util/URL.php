<?php

namespace mii\util;

class URL
{

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
     * @param   mixed $protocol Protocol string or boolean
     * @return  string
     */
    public static function base($protocol = null): string {
        if ($protocol === null) {
            return \Mii::$app->base_url;
        }

        if ($protocol === TRUE) {
            return \Mii::$app->request->get_hostname() . \Mii::$app->base_url;
        }

        if ($protocol !== '//')
            $protocol = $protocol . '://';

        $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];

        return $protocol . $domain . \Mii::$app->base_url;
    }

    /**
     * Fetches an absolute site URL based on a URI segment.
     *
     *     echo URL::site('foo/bar');
     *
     * @param   string $uri Site URI to convert
     * @param   mixed $protocol Protocol string or true
     * @return  string
     */
    public static function site(string $uri = '', $protocol = null): string {
        // Chop off possible scheme, host, port, user and pass parts
        $path = preg_replace('~^[-a-z0-9+.]++://[^/]++/?~', '', trim($uri, '/'));

        if (preg_match('/[^\x00-\x7F]/S', $path)) {
            // Encode all non-ASCII characters, as per RFC 1738
            $path = preg_replace_callback('~([^/]+)~', '\mii\util\URL::_rawurlencode_callback', $path);
        }

        // Concat the URL
        return URL::base($protocol) . $path;
    }

    /**
     * Callback used for encoding all non-ASCII characters, as per RFC 1738
     * Used by URL::site()
     *
     * @param  array $matches Array of matches from preg_replace_callback()
     * @return string          Encoded string
     */
    protected static function _rawurlencode_callback($matches) {
        return rawurlencode($matches[0]);
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
     * @param   array $params Array of GET parameters
     * @param   boolean $use_get Include current request GET parameters
     * @return  string
     */
    public static function query(array $params = null, $use_get = null) {
        if ($use_get) {
            if ($params === NULL) {
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
        $query = http_build_query($params, '', '&');

        // Don't prepend '?' to an empty string
        return ($query === '') ? '' : ('?' . $query);
    }


    static public function current(array $params = null): string {
        return static::site(\Mii::$app->request->uri()) . static::query($params, true);
    }


    static public function back_url(string $default = null): string {
        if (isset($_GET['back_url']))
            return urldecode($_GET['back_url']);

        if ($default === null) {
            return URL::current();
        }

        return $default;
    }


}
