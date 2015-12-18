<?php

namespace mii\core;


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

    protected $routes;

    protected $group_order;

    protected $_routes_list;


    public function __construct($config = []) {
        foreach($config as $key => $value)
            $this->$key = $value;
    }

    public function init_routes() {

        // Sort groups
        if(count($this->routes) && $this->group_order) {
            $this->routes = array_merge(array_flip($this->group_order), $this->routes);
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

                $compiled = $this->compile_route($pattern, $params);

                $this->_routes_list[$pattern] = [
                    'name' => $name,
                    'pattern' => $pattern,
                    'path' => $is_closure ? false : $path,
                    'compiled' => $compiled,
                    'namespace' => $namespace,
                    'is_closure' => $is_closure,
                    'is_static' =>  (strpos($pattern, '<') === false AND strpos($pattern, '(') === false)
                ];

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


        if (strpos($uri, '<') === FALSE AND strpos($uri, '(') === FALSE)
        {
            // This is a static route, no need to replace anything

            if ( ! $this->is_external())
                return $uri;

            // If the localhost setting does not have a protocol
            if (strpos($this->_defaults['host'], '://') === FALSE)
            {
                // Use the default defined protocol
                $params['host'] = Route::$default_protocol.$this->_defaults['host'];
            }
            else
            {
                // Use the supplied host with protocol
                $params['host'] = $this->_defaults['host'];
            }

            // Compile the final uri and return it
            return rtrim($params['host'], '/').'/'.$uri;
        }

    }

}