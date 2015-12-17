<?php

namespace mii\core;


class Route {

    // Defines the pattern of a <segment>
    const REGEX_KEY     = '<([a-zA-Z0-9_]++)>';

    // What can be part of a <segment> value
    const REGEX_SEGMENT = '[^/.,;?\n]++';

    // What must be escaped in the route regex
    const REGEX_ESCAPE  = '[.\\+*?[^\\]${}=!|]';


    protected $_pattern;

    protected $_compiled;

    protected $_path;

    protected $_callback;

    protected $_parameters = [
        'id' => '[0-9]+',
        'slug' => '[a-zA-Z0-9-_.]+'
    ];



    /**
     * Creates a new route. Sets the URI and regular expressions for keys.
     * Routes should always be created with [Route::set] or they will not
     * be properly stored.
     *
     *     $route = new Route($uri, $regex);
     *
     * The $uri parameter should be a string for basic regex matching.
     *
     *
     * @param   string  $uri    route URI pattern
     * @param   array   $regex  key patterns
     * @return  void
     * @uses    Route::_compile
     */
    public function __construct($pattern = null, $path = null, $parameters = null)
    {

        if($pattern !== null) {
            $this->_pattern = $pattern;
        }

        if($path instanceof \Closure) {

            $this->_callback = $path;

        } elseif ($path !== null)
        {
            $this->_path = $path;
        }

        if($parameters !== null) {
            foreach($parameters as $name => $value) {
                $this->_parameters[$name] = $value;
            }
        }
    }


    public function compile()
    {
        // The URI should be considered literal except for keys and optional parts
        // Escape everything preg_quote would escape except for : ( ) < >
        $expression = preg_replace('#'. static::REGEX_ESCAPE.'#', '\\\\$0', $this->_pattern);

        if (strpos($expression, '(') !== FALSE)
        {
            // Make optional parts of the URI non-capturing and optional
            $expression = str_replace(['(', ')'], ['(?:', ')?'], $expression);
        }

        // Insert default regex for keys
        $expression = str_replace(['<', '>'], ['(?P<', '>'. static::REGEX_SEGMENT.')'], $expression);

        $search = $replace = [];
        foreach ($this->_parameters as $key => $value)
        {
            $search[]  = "<$key>". static::REGEX_SEGMENT;
            $replace[] = "<$key>$value";
        }

        // Replace the default regex with the user-specified regex
        $expression = str_replace($search, $replace, $expression);


        $this->_compiled = '#^'.$expression.'$#uD';
    }


    public function match($uri)
    {
        if($this->_compiled === null)
            $this->compile();

        echo $this->_compiled;

        if ( ! preg_match($this->_compiled, $uri, $matches))
            return FALSE;

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


        if($this->_callback) {
            return call_user_func($this->_callback, [
                $uri,
                $matches
            ]);
        }

        if(!isset($params['action']))
            $params['action'] = 'index';

        $path = $this->_path;

        foreach($params as $key => $value) {
            $path = str_replace('<'.$key.'>', $value, $path);
        }

        if(strpos($path, ':') !== false) {
            list($params['controller'], $params['action']) = explode(':', $path);
        } else {
            $params['controller'] = $path;
            $params['action'] = $params['action'];
        }

        $params['controller'] = explode('/', $params['controller']);
        $filename = ucfirst(array_pop($params['controller']));
        $params['controller'] = implode('/', $params['controller']).'/'.$filename;

        return $params;
    }



}