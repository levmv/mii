<?php

namespace mii\web;


class Router {

    // Defines the pattern of a <segment>
    const REGEX_KEY     = '<([a-zA-Z0-9_]++)>';

    // What can be part of a <segment> value
    const REGEX_SEGMENT = '[^/.,;?\n]++';

    // What must be escaped in the route regex
    const REGEX_ESCAPE  = '[.\\+*?[^\\]${}=!|]';

    protected $default_parameters = [
        'id' => '[0-9]+',
        'slug' => '[a-zA-Z0-9-_.]+'
    ];

    protected $_defaults = ['action' => 'index'];

    protected $cache = false;

    protected $routes;

    protected $order;

    protected $_routes_list;

    protected $_named_routes;


    public function __construct($config = []) {
        foreach($config as $key => $value)
            $this->$key = $value;

        $this->init();
    }

    public function init() {

        if($this->cache) {

        } else {
            $this->init_routes();
        }


    }

    public function init_routes() {

        // Sort groups
        if(count($this->routes) && $this->order) {
            $this->routes = array_merge(array_flip($this->order), $this->routes);
        }

        foreach($this->routes as $namespace => $group) {

            foreach($group as $pattern => $value) {

                $is_closure = false;
                $name = '';

                if(is_array($value)) {
                    $path = $value['path'];
                    $name = isset($value['name']) ? $value['name'] : '';

                    $params = isset($value['params'])
                                ? array_merge($this->default_parameters, $value['params'])
                                : $this->default_parameters;
                } else {
                    $path = $value;
                    $params = $this->default_parameters;
                }

                $is_closure = ($value instanceof \Closure) ;

                if((strpos($pattern, '<') === false AND strpos($pattern, '(') === false)) {
                    // static route
                    $compiled = $pattern;
                    $is_static = true;
                } else {
                    $compiled = $this->compile_route($pattern, $params);
                    $is_static = false;
                }

                $this->_routes_list[$compiled] = [
                    'name' => $name,
                    'pattern' => $pattern,
                    'path' => $is_closure ? false : $path,
                    'compiled' => $compiled,
                    'namespace' => $namespace,
                    'is_closure' => $is_closure,
                    'is_static' => $is_static
                ];

                if($name) {
                    $this->_named_routes[$name] = $this->_routes_list[$pattern];
                }
            }
        }
    }

    public function compile_route($pattern, $parameters) {

        // The URI should be considered literal except for keys and optional parts
        // Escape everything preg_quote would escape except for : ( ) < >
        $expression = preg_replace('#'. static::REGEX_ESCAPE.'#', '\\\\$0', $pattern);

        if (strpos($expression, '(') !== FALSE)
        {
            // Make optional parts of the URI non-capturing and optional
            $expression = str_replace(['(', ')'], ['(?:', ')?'], $expression);
        }

        // Insert default regex for keys
        $expression = str_replace(['<', '>'], ['(?P<', '>'. static::REGEX_SEGMENT.')'], $expression);

        $search = $replace = [];
        foreach ($parameters as $key => $value)
        {
            $search[]  = "<$key>". static::REGEX_SEGMENT;
            $replace[] = "<$key>$value";
        }

        // Replace the default regex with the user-specified regex
        $expression = str_replace($search, $replace, $expression);

        return '#^'.$expression.'$#uD';
    }


    public function match($uri) {

        foreach($this->_routes_list as $route) {
            $result = $this->match_route($uri, $route);

            if($result === false)
                continue;

            return $result;
        }
    }

    protected function match_route($uri, $route) {

        if($route['is_static']) {
            if( $uri !== $route['pattern'])
                return false;

            $matches = [];

        } else {
            if ( ! preg_match($route['compiled'], $uri, $matches))
                return false;
        }

        $params = [];
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


        if($route['is_closure']) {
            return true;
            /*  return call_user_func($this->_callback, [
                  $uri,
                  $matches
              ]);*/
        }

        if(!isset($params['action']))
            $params['action'] = 'index';

        $path = $route['path'];

        foreach($params as $key => $value) {
            $path = str_replace('<'.$key.'>', $value, $path);
        }

        if(strpos($path, ':') !== false) {
            list($params['controller'], $params['action']) = explode(':', $path);
        } else {
            $params['controller'] = $path;
        }

        $params['controller'] = explode('/', $params['controller']);
        $filename = ucfirst(array_pop($params['controller']));
        $params['controller'] = $route['namespace'].implode('\\', $params['controller']).'\\'.$filename;

        return $params;
    }


    public function url($name, $params = []) {

        if(!isset($this->_routes_list[$name])) {
            throw new RouteException('Route :name doesnt exist', [':name' => $name]);
        }

        $route = $this->_routes_list[$name];


        if ($route['is_static'])
        {
            // This is a static route, no need to replace anything
            // TODO: host?
            return '/'.$route['pattern'];
        }


        // TODO:

        // Keep track of whether an optional param was replaced
        $provided_optional = FALSE;

        while (preg_match('#\([^()]++\)#', $route['compiled'], $match))
        {

            // Search for the matched value
            $search = $match[0];

            // Remove the parenthesis from the match as the replace
            $replace = substr($match[0], 1, -1);

            while (preg_match('#'.Route::REGEX_KEY.'#', $replace, $match))
            {
                list($key, $param) = $match;

                if (isset($params[$param]) AND $params[$param] !== Arr::get($this->_defaults, $param))
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
                        throw new RouteException('Required route parameter not passed: :param', [
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

        while (preg_match('#'.Route::REGEX_KEY.'#', $uri, $match))
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
                    throw new RouteException('Required route parameter not passed: :param', [
                        ':param' => $param,
                    ]);
                }
            }

            $uri = str_replace($key, $params[$param], $uri);
        }

        // Trim all extra slashes from the URI
        $uri = preg_replace('#//+#', '/', rtrim($uri, '/'));

        if ($this->is_external())
        {
            // Need to add the host to the URI
            $host = $this->_defaults['host'];

            if (strpos($host, '://') === FALSE)
            {
                // Use the default defined protocol
                $host = Route::$default_protocol.$host;
            }

            // Clean up the host and prepend it to the URI
            $uri = rtrim($host, '/').'/'.$uri;
        }

        return $uri;
    }

}