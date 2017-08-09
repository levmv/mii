<?php

namespace mii\core;


use mii\util\Arr;
use mii\util\URL;

class Router extends Component {

    // Defines the pattern of a {segment}
    const REGEX_KEY     = '{([a-zA-Z0-9_]++)}';

    // What can be part of a {segment} value
    const REGEX_SEGMENT = '[^/.,;?\n]++';

    // What must be escaped in the route regex
    const REGEX_ESCAPE  = '[.\\+*?[^\\]$<>=!|]';

    protected $default_parameters = [
        'id' => '[0-9]+',
        'slug' => '[a-zA-Z0-9-_.]+',
        'path' => '[a-zA-Z0-9-_./]+'
    ];

    protected $_defaults = ['action' => 'index'];

    protected $ignore_slash = true;

    protected $cache = false;

    protected $cache_id = 'mii_core_router_routes';

    protected $cache_lifetime = 86400;

    protected $routes;

    protected $order;

    protected $_routes_list;

    protected $_named_routes;

    protected $_current_route;


    public function init(array $config = []) : void {

        parent::init($config);

        if($this->cache) {

            list($this->_routes_list, $this->_named_routes) = get_cached($this->cache_id, [null, null]);
            if($this->_routes_list === null)
                $this->init_routes();

        } else {

            $this->init_routes();

        }
    }


    /**
     * Process route list.
     * As result: $this->_routes_list and $this->_named_routes
     */
    public function init_routes() : void {

        // Sort groups
        if($this->order !== null && count($this->routes)) {
            $this->routes = array_merge(array_flip($this->order), $this->routes);
        }

        foreach($this->routes as $namespace => $group) {

            foreach($group as $pattern => $value) {

                $result = [
                    'pattern' => '',
                    'path' => '',
                    'namespace' => $namespace,
                    'callback' => false,
                    'is_static' => false,
                ];

                $params = [];
                $name = false;

                $is_static = ((strpos($pattern, '{') === false AND strpos($pattern, '(') === false));

                if(is_array($value)) {
                    $result['path'] = $value['path'];

                    if(isset($value['name']))
                        $name = $value['name'];

                    if(isset($value['callback'])) {
                        $result['callback'] = ($value['callback'] instanceof \Closure) ? true : $value['callback'];
                    }
                    if(!$is_static) {
                        $params = isset($value['params'])
                            ? array_merge($this->default_parameters, $value['params'])
                            : $this->default_parameters;
                    }
                    if(isset($value['values'])) {
                        $result['values'] = $value['values'];
                    }

                } elseif(is_string($value)) {
                    $result['path'] = $value;
                    if(!$is_static) {
                        $params = $this->default_parameters;
                    }
                } elseif($value instanceof \Closure) {
                    $result['callback'] = true;
                }

                $key = $result['pattern'] = (string) $pattern; // php automatically converts array key like "555" to integer :(

                if($is_static) {
                    $result['is_static'] = true;
                } else {
                    $key = $this->compile_route($pattern, $params);
                }

                $this->_routes_list[$key] = $result;

                $name = $name ? $name : $result['path'];
                $this->_named_routes[$name] = $key;

            }
        }
        if($this->cache) {
            cache($this->cache_id, [$this->_routes_list, $this->_named_routes], $this->cache_lifetime);
        }
    }

    protected function compile_route(string $pattern, array $parameters) : string {

        // The URI should be considered literal except for keys and optional parts
        // Escape everything preg_quote would escape except for : ( ) { }
        $expression = preg_replace('#'. static::REGEX_ESCAPE.'#', '\\\\$0', $pattern);

        if (strpos($expression, '(') !== FALSE)
        {
            // Make optional parts of the URI non-capturing and optional
            $expression = str_replace(['(', ')'], ['(?:', ')?'], $expression);
        }

        // Insert default regex for keys
        $expression = str_replace(['{', '}'], ['(?\'', '\''. static::REGEX_SEGMENT.')'], $expression);

        $search = $replace = [];
        foreach ($parameters as $key => $value)
        {
            $search[]  = "'".$key."'". static::REGEX_SEGMENT;
            $replace[] = "'".$key."'".$value;
        }

        // Replace the default regex with the user-specified regex
        $expression = str_replace($search, $replace, $expression);

        return '#^'.$expression.'$#uD';
    }


    public function match(string $uri) {

        $benchmark = false;
        if (config('debug')) {
            $benchmark = \mii\util\Profiler::start('Router match', $uri);
        }

        if($uri !== '/') {
            $uri = trim($uri, '//');
        }

        foreach($this->_routes_list as $pattern => $route) {
            $result = $this->match_route($uri, $pattern, $route);

            if($result !== false) {
                $this->_current_route = $pattern;

                if ($benchmark) {
                    \mii\util\Profiler::stop($benchmark);
                }

                return $result;
            }
        }

        if ($benchmark) {
            \mii\util\Profiler::stop($benchmark);
        }

        return false;
    }

    protected function match_route(string $uri, string $pattern, array $route) {

        if($route['is_static'] === true) {

            if( $uri !== $pattern)
                return false;

            $matches = [];

        } else {
            if ( ! preg_match($pattern, $uri, $matches))
                return false;
        }

        $params = isset($route['values']) ? $route['values'] : [];

        foreach ($matches as $key => $value)
        {
            if (is_int($key))
            {
                // Skip all unnamed keys
                continue;
            }

            // Set the value for all matched keys
            $params[$key] = $value;
        }

        if($route['callback']) {

            if($route['callback'] === true) { // mean its closure
                $original_route = $this->routes[$route['namespace']][$route['pattern']];
                $callback = is_array($original_route) ? $original_route['callback'] : $original_route;
            } else {
               $callback = $route['callback'];
            }
            $params = call_user_func($callback, $matches);

            $params['controller'] = $route['namespace'].'\\'.$params['controller'];

        } else {

            $path = $route['path'];

            foreach($params as $key => $value) {
                if(is_string($value)) {
                    $path = str_replace('{'.$key.'}', $value, $path);
                }
            }

            if(strpos($path, ':') !== false) {
                list($path, $params['action']) = explode(':', $path);
            }

            $path = explode('/', $path);

            $filename = array_pop($path);

            if(isset($params['controller'])) {
                $filename = $params['controller'];
            }

            $params['controller'] = (count($path))
                ? $route['namespace'].'\\'.implode("\\", $path)."\\".ucfirst($filename)
                : $route['namespace'].'\\'.ucfirst($filename);
        }

        if(!isset($params['action']))
            $params['action'] = 'index';

        return $params;
    }


    public function current_route() {
        return $this->_routes_list[$this->_current_route];
    }


    public function url(string $name, array $params = []) : string {

        if(!isset($this->_named_routes[$name])) {
            throw new InvalidRouteException('Route :name doesnt exist', [':name' => $name]);
        }

        $route = $this->_routes_list[$this->_named_routes[$name]];

        if ($route['is_static'])
        {
            // This is a static route, no need to replace anything
            return URL::site($route['pattern']);
        }

        // Keep track of whether an optional param was replaced
        $provided_optional = false;

        $uri = $route['pattern'];

        while (preg_match('#\([^()]++\)#', $uri, $match))
        {
            // Search for the matched value
            $search = $match[0];

            // Remove the parenthesis from the match as the replace
            $replace = substr($match[0], 1, -1);


            while (preg_match('#'.static::REGEX_KEY.'#', $replace, $match))
            {
                list($key, $param) = $match;


                if ($params !== null AND isset($params[$param]) AND $params[$param] !== Arr::get($this->_defaults, $param))
                {
                    // Future optional params should be required
                    $provided_optional = TRUE;

                    // Replace the key with the parameter value
                    $replace = str_replace($key, $params[$param], $replace);
                }
                elseif ($provided_optional)
                {
                    // Look for a default
                    if (isset($this->_defaults[$param]))
                    {
                        $replace = str_replace($key, $this->_defaults[$param], $replace);
                    }
                    else
                    {
                        // Ungrouped parameters are required
                        throw new InvalidRouteException('Required route parameter not passed: :param', [
                            ':param' => $param,
                        ]);
                    }
                }
                else
                {
                    // This group has missing parameters
                    $replace = '';
                    break;
                }

            }

            // Replace the group in the URI
            $uri = str_replace($search, $replace, $uri);
        }


        while (preg_match('#'.static::REGEX_KEY.'#', $uri, $match))
        {
            list($key, $param) = $match;

            if ( ! isset($params[$param]))
            {
                // Look for a default
                if (isset($this->_defaults[$param]))
                {
                    $params[$param] = $this->_defaults[$param];
                }
                else
                {
                    // Ungrouped parameters are required
                    throw new InvalidRouteException('Required route parameter not passed: :param', [
                        ':param' => $param,
                    ]);
                }
            }

            $uri = str_replace($key, $params[$param], $uri);
        }

        // Trim all extra slashes from the URI
        $uri = preg_replace('#//+#', '/', rtrim($uri, '/'));


        return URL::site($uri);
    }

}

