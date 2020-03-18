<?php declare(strict_types=1);

namespace mii\core;


use mii\util\URL;
use mii\web\Response;

class Router extends Component
{

    // Defines the pattern of a {segment}
    const REGEX_KEY = '{([a-zA-Z0-9_]++)}';

    // What can be part of a {segment} value
    const REGEX_SEGMENT = '[^/.,;?\n]++';

    // What must be escaped in the route regex
    const REGEX_ESCAPE = '[.\\+*?[^\\]$<>=!|]';

    private const R_COMPILED = 0;
    private const R_PATTERN = 1;
    private const R_PATH = 2;
    private const R_NAMESPACE = 3;
    private const R_VALUES = 4;
    private const R_CALLBACK = 5;
    private const R_METHOD = 6;


    protected $default_parameters = [
        'id' => '[0-9]+',
        'slug' => '[a-zA-Z0-9-_.]+',
        'path' => '[a-zA-Z0-9-_./]+'
    ];

    protected ?string $cache = null;

    protected $cache_id = 'mii_core_router_routes';

    protected $cache_lifetime = 86400;

    protected $rest_mode = false;

    protected $routes;

    protected $order;

    protected $_routes_list;

    protected $_named_routes;

    protected $_namespaces = [];

    protected $_current_route;


    public function init(array $config = []): void
    {

        parent::init($config);

        if ($this->cache) {

            list($this->_routes_list, $this->_named_routes, $this->_namespaces) = \Mii::$app->get($this->cache)->get($this->cache_id, [null, null, null]);
            if ($this->_routes_list === null)
                $this->init_routes();

        } else {
            $this->init_routes();
        }
    }


    /**
     * Process route list.
     * As result: $this->_routes_list and $this->_named_routes
     */
    public function init_routes(): void
    {

        // Sort groups
        if ($this->order !== null && \count($this->routes)) {
            $this->routes = \array_merge(\array_flip($this->order), $this->routes);
        }

        $namespace_index = 0;
        $route_index = 0;
        foreach ($this->routes as $namespace => $group) {

            $this->_namespaces[] = $namespace;

            foreach ($group as $pattern => $value) {

                $pattern = \mb_strtolower($pattern, 'utf-8');
                $method = false;

                if ($this->rest_mode) {
                    \preg_match('/^(get|post|put|delete):/', $pattern, $matches);
                    if (\count($matches)) {
                        $method = $matches[1];
                        $pattern = \preg_replace('/^(get|post|put|delete):/', '', $pattern);
                    }
                }

                $result = [
                    static::R_COMPILED => '',
                    static::R_PATTERN => $pattern,
                    static::R_PATH => ''
                    //static::R_NAMESPACE => $namespace_index
                ];

                if ($this->rest_mode) {
                    $result[static::R_METHOD] = $method;
                }

                if ($namespace_index !== 0)
                    $result[static::R_NAMESPACE] = $namespace_index;

                $params = [];
                $name = false;

                $is_static = ((\strpos($pattern, '{') === false AND \strpos($pattern, '(') === false));

                if (\is_array($value)) {
                    $result[static::R_PATH] = $value['path'];

                    if (isset($value['name']))
                        $name = $value['name'];

                    if (isset($value['callback'])) {
                        if ($value['callback'] instanceof \Closure) {
                            assert($this->cache === false, "Closure routes not recommended with enabled cache");
                            $result[static::R_CALLBACK] = true;
                        } else {
                            $result[static::R_CALLBACK] = $value['callback'];
                        }
                    }
                    if (!$is_static) {
                        $params = isset($value['params'])
                            ? \array_merge($this->default_parameters, $value['params'])
                            : $this->default_parameters;
                    }
                    if (isset($value['values'])) {
                        $result[static::R_VALUES] = $value['values'];
                    }

                    if (isset($value['method']))
                        $result[static::R_METHOD] = $value['method'];

                } elseif (\is_string($value)) {
                    $result[static::R_PATH] = $value;
                    if (!$is_static) {
                        $params = $this->default_parameters;
                    }
                } elseif ($value instanceof \Closure) {
                    $result[static::R_CALLBACK] = true;
                }

                if (!$is_static) {
                    $result[static::R_COMPILED] = $this->compile_route($pattern, $params);
                }

                $this->_routes_list[] = $result;

                $name = $name ? $name : $result[static::R_PATH];

                $this->_named_routes[$name] = $route_index;

                $route_index++;
            }
            $namespace_index++;
        }

        if ($this->cache) {
            \Mii::$app->get($this->cache)->set($this->cache_id, [$this->_routes_list, $this->_named_routes, $this->_namespaces], $this->cache_lifetime);
        }
    }

    protected function compile_route(string $pattern, array $parameters): string
    {

        // The URI should be considered literal except for keys and optional parts
        // Escape everything preg_quote would escape except for : ( ) { }
        $expression = \preg_replace('#' . static::REGEX_ESCAPE . '#', '\\\\$0', $pattern);

        if (\strpos($expression, '(') !== FALSE) {
            // Make optional parts of the URI non-capturing and optional
            $expression = \str_replace(['(', ')'], ['(?:', ')?'], $expression);
        }

        // Insert default regex for keys
        $expression = \str_replace(['{', '}'], ['(?\'', '\'' . static::REGEX_SEGMENT . ')'], $expression);

        $search = $replace = [];
        foreach ($parameters as $key => $value) {
            $search[] = "'" . $key . "'" . static::REGEX_SEGMENT;
            $replace[] = "'" . $key . "'" . $value;
        }

        // Replace the default regex with the user-specified regex
        $expression = \str_replace($search, $replace, $expression);

        return '#^' . $expression . '$#uD';
    }


    public function match(string $uri)
    {

        if ($uri !== '/') {
            $uri = \trim($uri, '//');
        }

        foreach ($this->_routes_list as $index => $route) {
            $result = $this->match_route($uri, $route);

            if ($result !== false) {
                $this->_current_route = $index;
                return $result;
            }
        }

        return false;
    }

    protected function match_route(string $uri, array $route)
    {

        if (empty($route[static::R_COMPILED])) {

            if ($uri !== $route[static::R_PATTERN])
                return false;

            $matches = [];

        } else {
            if (!\preg_match($route[static::R_COMPILED], $uri, $matches))
                return false;
        }

        if ($this->rest_mode AND isset($route[static::R_METHOD]) AND $route[static::R_METHOD] !== false) {
            if (\strtolower(\Mii::$app->request->method()) !== $route[static::R_METHOD])
                return false;
        }

        $params = $route[static::R_VALUES] ?? [];

        foreach ($matches as $key => $value) {
            if (\is_int($key)) {
                // Skip all unnamed keys
                continue;
            }

            // Set the value for all matched keys
            $params[$key] = $value;
        }

        $namespace = isset($route[static::R_NAMESPACE])
            ? $this->_namespaces[$route[static::R_NAMESPACE]]
            : $this->_namespaces[0];

        if (isset($route[static::R_CALLBACK])) {
            if ($route[static::R_CALLBACK] === true) { // mean its closure
                $original_route = $this->routes[$namespace][$route[static::R_PATTERN]];
                $callback = \is_array($original_route) ? $original_route['callback'] : $original_route;
            } else {
                $callback = $route[static::R_CALLBACK];
            }
            $params = \call_user_func($callback, $matches);

            if ($params instanceof Response)
                return $params;

            $params['controller'] = $namespace . '\\' . $params['controller'];

        } else {

            $path = $route[static::R_PATH];

            foreach ($params as $key => $value) {
                if (\is_string($value)) {
                    $path = \str_replace('{' . $key . '}', $value, $path);
                }
            }

            if (strpos($path, ':') !== false) {
                list($path, $params['action']) = \explode(':', $path);
            }

            $path = \explode('/', $path);

            $filename = \array_pop($path);

            if (isset($params['controller'])) {
                $filename = $params['controller'];
            }

            $params['controller'] = (\count($path))
                ? $namespace . '\\' . \implode("\\", $path) . "\\" . \ucfirst($filename)
                : $namespace . '\\' . \ucfirst($filename);
        }

        if (!isset($params['action']))
            $params['action'] = 'index';

        return $params;
    }


    public function current_route()
    {
        return $this->_routes_list[$this->_current_route];
    }


    public function url(string $name, array $params = []): string
    {

        if (!isset($this->_named_routes[$name])) {
            throw new InvalidRouteException('Route :name doesnt exist', [':name' => $name]);
        }

        $route = $this->_routes_list[$this->_named_routes[$name]];

        if (empty($route[static::R_COMPILED])) {
            // This is a static route, no need to replace anything
            return URL::site($route[static::R_PATTERN]);
        }

        // Keep track of whether an optional param was replaced
        $provided_optional = false;

        $uri = $route[static::R_PATTERN];

        $defaults = ['action' => 'index'];

        while (preg_match('#\([^()]++\)#', $uri, $match)) {
            // Search for the matched value
            $search = $match[0];

            // Remove the parenthesis from the match as the replace
            $replace = substr($match[0], 1, -1);


            while (preg_match('#' . static::REGEX_KEY . '#', $replace, $match)) {
                list($key, $param) = $match;


                if ($params !== null AND isset($params[$param]) AND $params[$param] !== ($defaults[$param] ?? null)) {
                    // Future optional params should be required
                    $provided_optional = TRUE;

                    // Replace the key with the parameter value
                    $replace = str_replace($key, $params[$param], $replace);
                } elseif ($provided_optional) {
                    // Look for a default
                    if (isset($defaults[$param])) {
                        $replace = str_replace($key, $defaults[$param], $replace);
                    } else {
                        // Ungrouped parameters are required
                        throw new InvalidRouteException("Required route parameter not passed: $param");
                    }
                } else {
                    // This group has missing parameters
                    $replace = '';
                    break;
                }

            }

            // Replace the group in the URI
            $uri = str_replace($search, $replace, $uri);
        }


        while (preg_match('#' . static::REGEX_KEY . '#', $uri, $match)) {
            list($key, $param) = $match;

            if (!isset($params[$param])) {
                // Look for a default
                if (isset($this->_defaults[$param])) {
                    $params[$param] = $this->_defaults[$param];
                } else {
                    // Ungrouped parameters are required
                    throw new InvalidRouteException("Required route parameter not passed: $param");
                }
            }

            $uri = str_replace($key, $params[$param], $uri);
        }

        // Trim all extra slashes from the URI
        $uri = preg_replace('#//+#', '/', rtrim($uri, '/'));


        return URL::site($uri);
    }

}

