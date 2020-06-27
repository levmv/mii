<?php declare(strict_types=1);

namespace mii\core;

use mii\util\Url;
use mii\web\Response;

class Router extends Component
{

    // Defines the pattern of a {segment}
    protected const REGEX_KEY = '{([a-zA-Z0-9_]++)}';

    // What can be part of a {segment} value
    protected const REGEX_SEGMENT = '[^/.,;?\n]++';

    // What must be escaped in the route regex
    protected const REGEX_ESCAPE = '[.\\+*?[^\\]$<>=!|]';

    public const R_COMPILED = 0;
    public const R_PATTERN = 1;
    public const R_PATH = 2;
    public const R_METHOD = 3;
    public const R_NAMESPACE = 4;
    public const R_VALUES = 5;

    public string $namespace = 'app\\controllers';

    protected array $default_parameters = [
        'id' => '[0-9]+',
        'slug' => '[a-zA-Z0-9-_.]+',
        'path' => '[a-zA-Z0-9-_./]+',
    ];

    protected $cache = null;

    protected $cache_id = 'mii_core_router_routes';

    protected $cache_lifetime = 86400;

    protected bool $rest_mode = false;

    protected array $routes;

    protected $order;

    protected array $_routes_list;

    protected array $_named_routes;

    protected $_current_route;


    public function init(array $config = []): void
    {
        parent::init($config);

        if ($this->cache) {
            [$this->_routes_list, $this->_named_routes] = \Mii::$app->get($this->cache)->get($this->cache_id, [[], []]);
            if (empty($this->_routes_list)) {
                $this->initRoutes();
            }
        } else {
            $this->initRoutes();
        }
    }


    /**
     * Process route list.
     * As result: $this->_routes_list and $this->_named_routes
     */
    public function initRoutes(): void
    {
        $route_index = 0;

        foreach ($this->routes as $pattern => $value) {

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
                static::R_PATH => '',
            ];

            if ($this->rest_mode) {
                $result[static::R_METHOD] = $method;
            }

            $params = null;
            $name = false;

            $is_static = ((\strpos($pattern, '{') === false and \strpos($pattern, '(') === false));

            if (\is_array($value)) {
                $result[static::R_PATH] = $value['path'];

                $result[static::R_NAMESPACE] = $value['namespace'] ?? null;

                if (isset($value['name'])) {
                    $name = $value['name'];
                }

                if (!$is_static && isset($value['params'])) {
                    $params = \array_merge($this->default_parameters, $value['params']);
                }
                if (isset($value['values'])) {
                    $result[static::R_VALUES] = $value['values'];
                }
                if (isset($value['method'])) {
                    $result[static::R_METHOD] = $value['method'];
                }
            } elseif (\is_string($value)) {
                $result[static::R_PATH] = $value;
            }

            if (!$is_static) {
                $result[static::R_COMPILED] = $this->compileRoute($pattern, $params);
            }

            $this->_routes_list[] = $result;

            $name = $name ?: $result[static::R_PATH];

            $this->_named_routes[$name] = $route_index;

            $route_index++;
        }

        if ($this->cache) {
            \Mii::$app->get($this->cache)->set($this->cache_id, [$this->_routes_list, $this->_named_routes], $this->cache_lifetime);
        }
    }

    protected function compileRoute(string $pattern, array $parameters = null): string
    {
        $parameters ??= $this->default_parameters;

        // The URI should be considered literal except for keys and optional parts
        // Escape everything preg_quote would escape except for : ( ) { }
        $expression = \preg_replace('#' . static::REGEX_ESCAPE . '#', '\\\\$0', $pattern);

        if (\strpos($expression, '(') !== false) {
            // Make optional parts of the URI non-capturing and optional
            $expression = \str_replace(['(', ')'], ['(?:', ')?'], $expression);
        }

        // Insert default regex for keys
        $expression = \str_replace(['{', '}'], ['(?\'', '\'' . static::REGEX_SEGMENT . ')'], $expression);

        $search = $replace = [];
        foreach ($parameters as $key => $value) {
            $search[] = "'$key'" . static::REGEX_SEGMENT;
            $replace[] = "'$key'" . $value;
        }

        // Replace the default regex with the user-specified regex
        $expression = \str_replace($search, $replace, $expression);

        return "#^$expression$#uD";
    }


    public function match(string $uri)
    {
        if ($uri !== '/') {
            $uri = \trim($uri, '//');
        }

        foreach ($this->_routes_list as $index => $route) {
            $result = $this->matchRoute($uri, $route);

            if ($result !== false) {
                $this->_current_route = $index;
                return $result;
            }
        }

        return false;
    }

    protected function matchRoute(string $uri, array $route)
    {
        if (empty($route[static::R_COMPILED])) {
            if ($uri !== $route[static::R_PATTERN]) {
                return false;
            }

            $matches = [];
        } else {
            if (!\preg_match($route[static::R_COMPILED], $uri, $matches)) {
                return false;
            }
        }

        if ($this->rest_mode && isset($route[static::R_METHOD]) && $route[static::R_METHOD] !== false) {
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

        $namespace = $route[static::R_NAMESPACE] ?? $this->namespace;

        $path = $route[static::R_PATH];

        foreach ($params as $key => $value) {
            if (\is_string($value)) {
                $path = \str_replace('{' . $key . '}', $value, $path);
            }
        }

        if (\strpos($path, ':') !== false) {
            [$path, $params['action']] = \explode(':', $path);
        }

        $path = \explode('/', $path);

        $filename = \array_pop($path);

        if (isset($params['controller'])) {
            $filename = $params['controller'];
        }

        $params['controller'] = (\count($path))
            ? $namespace . '\\' . \implode('\\', $path) . '\\' . \ucfirst($filename)
            : $namespace . '\\' . \ucfirst($filename);

        if (!isset($params['action'])) {
            $params['action'] = 'index';
        }

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
            return Url::site($route[static::R_PATTERN]);
        }

        // Keep track of whether an optional param was replaced
        $provided_optional = false;

        $uri = $route[static::R_PATTERN];

        $defaults = ['action' => 'index'];

        while (\preg_match('#\([^()]++\)#', $uri, $match)) {
            // Search for the matched value
            $search = $match[0];

            // Remove the parenthesis from the match as the replace
            $replace = \substr($match[0], 1, -1);


            while (\preg_match('#' . static::REGEX_KEY . '#', $replace, $match)) {
                [$key, $param] = $match;


                if ($params !== null && isset($params[$param]) && $params[$param] !== ($defaults[$param] ?? null)) {
                    // Future optional params should be required
                    $provided_optional = true;

                    // Replace the key with the parameter value
                    $replace = \str_replace($key, $params[$param], $replace);
                } elseif ($provided_optional) {
                    // Look for a default
                    if (isset($defaults[$param])) {
                        $replace = \str_replace($key, $defaults[$param], $replace);
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
            $uri = \str_replace($search, $replace, $uri);
        }


        while (\preg_match('#' . static::REGEX_KEY . '#', $uri, $match)) {
            [$key, $param] = $match;

            if (!isset($params[$param])) {
                // Look for a default
                if (isset($this->_defaults[$param])) {
                    $params[$param] = $this->_defaults[$param];
                } else {
                    // Ungrouped parameters are required
                    throw new InvalidRouteException("Required route parameter not passed: $param");
                }
            }

            $uri = \str_replace($key, $params[$param], $uri);
        }

        // Trim all extra slashes from the URI
        $uri = \preg_replace('#//+#', '/', \rtrim($uri, '/'));


        return Url::site($uri);
    }


    public function getCompiledData() : array
    {
        return [$this->_routes_list, $this->_named_routes];
    }
}
