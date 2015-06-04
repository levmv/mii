<?php

namespace mii\web;

use mii\core\Route;
use mii\util\Arr;


class Request extends \mii\core\Request
{

    // HTTP Methods
    const GET = 'GET';
    const POST = 'POST';
    const PUT = 'PUT';
    const DELETE = 'DELETE';
    const HEAD = 'HEAD';
    const OPTIONS = 'OPTIONS';
    const TRACE = 'TRACE';
    const CONNECT = 'CONNECT';

    /**
     * @var  string  method: GET, POST, PUT, DELETE, HEAD, etc
     */
    protected $_method = 'GET';

    /**
     * @var  boolean
     */
    protected $_secure = false;

    /**
     * @var  string  referring URL
     */
    protected $_referrer;


    /**
     * @var  string the body
     */
    protected $_body;


    /**
     * @var  string  the URI of the request
     */
    protected $_uri;


    /**
     * @var array    query parameters
     */
    protected $_get = [];

    /**
     * @var array    post parameters
     */
    protected $_post = [];


    public function init($uri = null)
    {

        if ($uri !== null) {
            $this->uri(uri);
        } else {
            // Attempt to guess the proper URI
            $this->uri(Request::detect_uri());
        }

        if (isset($_SERVER['REQUEST_METHOD'])) {
            // Use the server request method
            $this->method($_SERVER['REQUEST_METHOD']);
        }

        if (!empty($_SERVER['HTTPS']) AND filter_var($_SERVER['HTTPS'], FILTER_VALIDATE_BOOLEAN)) {
            // This request is secure
            $this->secure(true);
        }


        // Store global GET and POST data in the initial request only
        $this->_get = &$_GET;
        $this->_post = &$_POST;

    }

    /**
     * Automatically detects the URI of the main request using PATH_INFO,
     * REQUEST_URI, PHP_SELF or REDIRECT_URL.
     *
     *
     * @return  string  URI of the main request
     * @throws  Mii\Exception
     */
    public function detect_uri()
    {
        if (!empty($_SERVER['PATH_INFO'])) {
            // PATH_INFO does not contain the docroot or index
            $uri = $_SERVER['PATH_INFO'];
        } else {
            // REQUEST_URI and PHP_SELF include the docroot and index

            if (isset($_SERVER['REQUEST_URI'])) {
                /**
                 * We use REQUEST_URI as the fallback value. The reason
                 * for this is we might have a malformed URL such as:
                 *
                 *  http://localhost/http://example.com/judge.php
                 *
                 * which parse_url can't handle. So rather than leave empty
                 * handed, we'll use this.
                 */
                $uri = $_SERVER['REQUEST_URI'];

                if ($request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
                    // Valid URL path found, set it.
                    $uri = $request_uri;
                }

                // Decode the request URI
                $uri = rawurldecode($uri);
            } elseif (isset($_SERVER['PHP_SELF'])) {
                $uri = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['REDIRECT_URL'])) {
                $uri = $_SERVER['REDIRECT_URL'];
            } else {
                // If you ever see this error, please report an issue at http://dev.kohanaphp.com/projects/kohana3/issues
                // along with any relevant information about your web server setup. Thanks!
                throw new Exception('Unable to detect the URI using PATH_INFO, REQUEST_URI, PHP_SELF or REDIRECT_URL');
            }

            // Get the path from the base URL, including the index file
            $base_url = parse_url('/', PHP_URL_PATH);

            if (strpos($uri, $base_url) === 0) {
                // Remove the base URL from the URI
                $uri = (string)substr($uri, strlen($base_url));
            }

        }

        return $uri;
    }


    /**
     * Processes the request, executing the controller action that handles this
     * request, determined by the [Route].
     *
     * 1. Before the controller action is called, the [Controller::before] method
     * will be called.
     * 2. Next the controller action will be called.
     * 3. After the controller action is called, the [Controller::after] method
     * will be called.
     *
     * By default, the output from the controller is captured and returned, and
     * no headers are sent.
     *
     *     $request->execute();
     *
     * @return  Response
     * @throws  Request_Exception
     * @throws  HTTP_Exception_404
     * @uses    [Kohana::$profiling]
     * @uses    [Profiler]
     */
    public function execute()
    {
        $processed = $this->match_route($this->_routes);

        if ($processed) {

            // Store the matching route
            $this->_route = $processed['route'];
            $params = $processed['params'];

            // Store the controller
            $this->_controller = $params['controller'];


            // Store the action
            $this->_action = (isset($params['action']))
                ? $params['action']
                : Route::$default_action;

            // These are accessible as public vars and can be overloaded
            unset($params['controller'], $params['action']);

            // Params cannot be changed once matched
            $this->_params = $params;
        }

        if (!$this->_route instanceof Route) {
            throw new HttpException(404, 'Unable to find a route to match the URI: :uri', [
                ':uri' => $this->_uri]);
        }


        if (MII_PROF) {
            // Set the benchmark name
            $benchmark = '"' . $this->uri() . '"';


            // Start benchmarking
            $benchmark = \mii\util\Profiler::start('Requests', $benchmark);
        }


        // Controller

        $controller = $this->controller();


        try {

            if (!class_exists($controller)) {

                throw new HttpException(404, 'The requested URL :uri was not found on this server.',
                    [':uri' => $this->uri()]
                );
            }
            if (extension_loaded('newrelic')) {
                newrelic_name_transaction($controller . '::' . $this->_action);

            }
            // Load the controller using reflection
            $class = new \ReflectionClass($controller);

            if ($class->isAbstract()) {
                throw new Exception(
                    'Cannot create instances of abstract :controller',
                    [':controller' => $controller]
                );
            }
            $response = new Response;

            // Create a new instance of the controller
            $controller = $class->newInstance($this, $response);

            // Run the controller's execute() method
            $response = $class->getMethod('execute')->invoke($controller, $params);


            if (!$response instanceof Response) {
                // Controller failed to return a Response.
                throw new Exception('Controller failed to return a Response');
            }
        } catch (RedirectHttpException $e) {

            $response->add_header('location', $e->url);

        }
        catch (HttpException $e) {

            // Get the response via the Exception
            //$response = $e->get_response();
            throw $e;
        } catch (Exception $e) {
            // Generate an appropriate Response object
            $response = Exception::handler($e);
        }


        if (MII_PROF) {
            \mii\util\Profiler::stop($benchmark);
        }

        return $response;
    }


    /**
     * Sets and gets the uri from the request.
     *
     * @param   string $uri
     * @return  mixed
     */
    public function uri($uri = NULL)
    {
        if ($uri === NULL) {
            // Act as a getter
            return empty($this->_uri) ? '/' : $this->_uri;
        }

        // Act as a setter
        $this->_uri = $uri;

        return $this;
    }


    public function get_user_agent()
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }


    /**
     * Gets or sets the HTTP method. Usually GET, POST, PUT or DELETE in
     * traditional CRUD applications.
     *
     * @param   string $method Method to use for this request
     * @return  mixed
     */
    public function method($method = NULL)
    {
        if ($method === NULL) {
            // Act as a getter
            return $this->_method;
        }

        // Act as a setter
        $this->_method = strtoupper($method);

        return $this;
    }

    /**
     * Getter/Setter to the security settings for this request. This
     * method should be treated as immutable.
     *
     * @param   boolean $secure is this request secure?
     * @return  mixed
     */
    public function secure($secure = NULL)
    {
        if ($secure === NULL)
            return $this->_secure;

        // Act as a setter
        $this->_secure = (bool)$secure;

        return $this;
    }


    /**
     * Gets or sets HTTP query string.
     *
     * @param   mixed $key Key or key value pairs to set
     * @param   string $value Value to set to a key
     * @return  mixed
     * @uses    Arr::path
     */
    public function query($key = NULL, $value = NULL)
    {
        if (is_array($key)) {
            // Act as a setter, replace all query strings
            $this->_get = $key;

            return $this;
        }

        if ($key === NULL) {
            // Act as a getter, all query strings
            return $this->_get;
        } elseif ($value === NULL) {
            // Act as a getter, single query string
            return Arr::path($this->_get, $key);
        }

        // Act as a setter, single query string
        $this->_get[$key] = $value;

        return $this;
    }

    /**
     * Gets HTTP POST parameters of the request.
     *
     * @param   mixed $key Parameter name
     * @param   string $default Default value if parameter does not exist
     * @return  mixed
     */
    public function post($key = null, $default = null)
    {
        if ($key === null) {
            return $this->_post;
        }

        return isset($this->_post[$key]) ? $this->_post[$key] : $default;
    }

    /**
     * Returns whether this is an AJAX (XMLHttpRequest) request.
     * @return boolean whether this is an AJAX (XMLHttpRequest) request.
     */
    public function is_ajax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    /**
     * Returns whether this is a PJAX request
     * @return boolean whether this is a PJAX request
     */
    public function is_pjax()
    {
        return $this->is_ajax() && !empty($_SERVER['HTTP_X_PJAX']);
    }

    private $_raw_body;

    /**
     * Returns the raw HTTP request body.
     * @return string the request body
     */
    public function raw_body()
    {
        if ($this->_raw_body === null) {
            $this->_raw_body = file_get_contents('php://input');
        }

        return $this->_raw_body;
    }

}