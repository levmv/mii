<?php declare(strict_types=1);

namespace mii\web;

use mii\core\Component;

class Request extends Component
{

    // HTTP Methods
    public const GET = 'GET';
    public const POST = 'POST';
    public const PUT = 'PUT';
    public const DELETE = 'DELETE';
    public const HEAD = 'HEAD';
    public const OPTIONS = 'OPTIONS';

    /**
     * @var  string  method: GET, POST, PUT, DELETE, HEAD, etc
     */
    protected string $_method = 'GET';

    public const SAME_SITE_LAX = 'Lax';
    public const SAME_SITE_STRICT = 'Strict';

    /**
     * @var  string  the URI of the request
     */
    protected string $_uri;

    protected ?string $_hostname = null;

    public bool $cookie_validation = false;

    public bool $enable_csrf_cookie = true;

    public string $csrf_token_name = 'csrf_token';

    protected $_csrf_token;

    /**
     * @var  string  Magic salt to add to the cookie
     */
    protected $cookie_salt;

    /**
     * @var  integer  Number of seconds before the cookie expires
     */
    protected int $cookie_expiration = 0;

    /**
     * @var  string  Restrict the path that the cookie is available to
     */
    public string $cookie_path = '/';

    /**
     * @var  string  Restrict the domain that the cookie is available to
     */
    public string $cookie_domain = '';

    /**
     * @var  boolean  Only transmit cookies over secure connections
     */
    public bool $cookie_secure = false;

    /**
     * @var  boolean  Only transmit cookies over HTTP, disabling Javascript access
     */
    public bool $cookie_httponly = true;


    public string $cookie_samesite = self::SAME_SITE_LAX;

    /**
     * @var  array   parameters from the route
     */
    public $params = [];

    /**
     * @var  string  controller to be executed
     */
    public string $controller;

    /**
     * @var  string  action to be executed in the controller
     */
    public string $action;


    public function init(array $config = []): void
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }

        $uri = \parse_url('http://domain.com' . $_SERVER['REQUEST_URI'], \PHP_URL_PATH);

        if (!\is_null(\Mii::$app->base_url) && \str_starts_with($uri, \Mii::$app->base_url)) {
            // Remove the base URL from the URI
            $uri = \substr($uri, \strlen(\Mii::$app->base_url));
        }

        $this->uri($uri);

        if (isset($_SERVER['REQUEST_METHOD'])) {
            // Use the server request method
            $this->method($_SERVER['REQUEST_METHOD']);
        }

        if ($this->_method === 'PUT') {
            \parse_str($this->rawBody(), $_POST);
        }
    }


    /**
     * Sets and gets the uri from the request.
     *
     * @param string|null $uri
     * @return  string
     */
    public function uri(string $uri = null): string
    {
        if ($uri === null) {
            return empty($this->_uri) ? '/' : $this->_uri;
        }

        return $this->_uri = $uri;
    }

    public function getHostname(): string
    {
        if (!$this->_hostname) {
            $http = $this->isSecure() ? 'https' : 'http';
            $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];
            $this->_hostname = $http . '://' . $domain;
        }

        return $this->_hostname;
    }

    public function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }


    public function getContentType(): string
    {
        return $_SERVER['CONTENT_TYPE'] ?? '';
    }

    public function getIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $_SERVER['REMOTE_ADDR'];
    }


    /**
     * Gets or sets the HTTP method. Usually GET, POST, PUT or DELETE in
     * traditional CRUD applications.
     *
     * @param string $method Method to use for this request
     * @return  mixed
     */
    public function method($method = null)
    {
        if ($method === null) {
            // Act as a getter
            return $this->_method;
        }

        // Act as a setter
        $this->_method = \strtoupper($method);

        return $this;
    }

    /**
     * Return if the request is sent via secure channel (https).
     * @return boolean if the request is sent via secure channel (https)
     */
    public function isSecure()
    {
        return (isset($_SERVER['HTTPS']) && (\strcasecmp($_SERVER['HTTPS'], 'on') === 0 || $_SERVER['HTTPS'] == 1))
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && \strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0);
    }


    /**
     *  Returns GET parameter with a given name. If name isn't specified, returns an array of all GET parameters.
     *
     * @param null $key
     * @param null $default
     * @return  mixed
     */
    public function get($key = null, $default = null)
    {
        if ($key) {
            return $_GET[$key] ?? $default;
        }

        return $_GET;
    }

    public function param($key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function input(string $name, $default = null)
    {
        return $_POST[$name] ?? $_GET[$name] ?? $default;
    }

    public function csrfToken($new = false)
    {
        if ($this->_csrf_token === null || $new) {
            if ($new || ($this->_csrf_token = $this->loadCsrfToken()) === null) {
                // Generate a new unique token
                $this->_csrf_token = \bin2hex(\random_bytes(20));

                // Store the new token
                if ($this->enable_csrf_cookie) {
                    $this->setCookie($this->csrf_token_name, $this->_csrf_token);
                } else {
                    \Mii::$app->session->set($this->csrf_token_name, $this->_csrf_token);
                }
            }
        }

        return $this->_csrf_token;
    }

    public function loadCsrfToken()
    {
        if ($this->enable_csrf_cookie) {
            return $this->getCookie($this->csrf_token_name);
        }

        return \Mii::$app->session->get($this->csrf_token_name);
    }


    /**
     * Gets HTTP POST parameters of the request.
     *
     * @param mixed  $key Parameter name
     * @param string $default Default value if parameter does not exist
     * @return  mixed
     */
    public function post(string $key = null, $default = null)
    {
        if ($key === null) {
            return $_POST;
        }

        return $_POST[$key] ?? $default;
    }

    /**
     * Returns whether this is an AJAX (XMLHttpRequest) request.
     * @return boolean whether this is an AJAX (XMLHttpRequest) request.
     */
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }


    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }


    private $_raw_body;

    /**
     * Returns the raw HTTP request body.
     * @return string the request body
     */
    public function rawBody(): string
    {
        if ($this->_raw_body === null) {
            $this->_raw_body = \file_get_contents('php://input');
        }

        return $this->_raw_body;
    }

    private ?array $_json_items = null;

    public function json($key = null, $default = null)
    {
        if (!$this->_json_items && stripos($this->getContentType(), 'application/json') !== false) {
            $this->_json_items = \json_decode(\file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        } else {
            $this->_json_items = [];
        }

        if ($key === null) {
            return $this->_json_items;
        }

        return $this->_json_items[$key] ?? $default;
    }


    /**
     * Gets the value of a cookie.
     *
     * If $cookie_validation set, then cookies without signatures will not
     * be returned. If the cookie signature is present, but invalid, the cookie
     * will be deleted.
     *
     * @param string $key cookie name
     * @param mixed  $default default value to return
     * @return  string
     */
    public function getCookie(string $key, $default = null)
    {
        if (!isset($_COOKIE[$key])) {
            // The cookie does not exist
            return $default;
        }

        if (!$this->cookie_validation) {
            return $_COOKIE[$key];
        }

        // Get the cookie value
        $cookie = $_COOKIE[$key];

        // Find the position of the split between salt and contents
        $sign_len = \strlen($this->salt($key, ''));

        if (\strlen($cookie) > $sign_len) {
            $sign = \substr($cookie, 0, $sign_len);
            $value = \substr($cookie, $sign_len);

            if ($this->salt($key, $value) === $sign) {
                // Cookie signature is valid
                return $value;
            }

            // The cookie signature is invalid, delete it
            $this->deleteCookie($key);
        }

        return $default;
    }


    /**
     * Sets a signed cookie. Note that all cookie values must be strings and no
     * automatic serialization will be performed!
     *
     * @param string $name name of cookie
     * @param string $value value of cookie
     * @param int|null $expiration lifetime in seconds
     * @return  boolean
     */
    public function setCookie(string $name, string $value, int $expiration = null): bool
    {
        if ($expiration === null) {
            // Use the default expiration
            $expiration = $this->cookie_expiration;
        }

        if ($expiration !== 0) {
            // The expiration is expected to be a UNIX timestamp
            $expiration += \time();
        }

        if ($this->cookie_validation) {
            // Add the salt to the cookie value
            $value = $this->salt($name, $value) . $value;
        }

        return \setcookie(
            $name,
            $value,
            [
                'expires' => $expiration,
                'path' => $this->cookie_path,
                'domain' => $this->cookie_domain,
                'secure' => $this->cookie_secure,
                'httpOnly' => $this->cookie_httponly,
                'sameSite' => $this->cookie_samesite,
            ]
        );
    }


    /**
     * Deletes a cookie by making the value null and expiring it.
     *
     *     Cookie::delete('theme');
     *
     * @param string $name cookie name
     * @return  boolean
     */
    public function deleteCookie(string $name): bool
    {
        // Remove the cookie
        unset($_COOKIE[$name]);

        // Nullify the cookie and make it expire
        return \setcookie($name, '', -86400, $this->cookie_path, $this->cookie_domain, $this->cookie_secure, $this->cookie_httponly);
    }


    /**
     * Generates a salt string for a cookie based on the name and value.
     *
     * @param string $name name of cookie
     * @param string $value value of cookie
     * @return  string
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function salt(string $name, string $value): string
    {
        // Require a valid salt
        if (!$this->cookie_salt) {
            throw new \InvalidArgumentException(
                'A valid cookie salt is required. Please set Request::$cookie_salt.'
            );
        }

        return \substr(\mii\util\Text::b64Encode(\md5($name . $value . $this->cookie_salt, true)), 0, 20);
    }
}
