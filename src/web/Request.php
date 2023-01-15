<?php declare(strict_types=1);

namespace mii\web;

use mii\core\Component;

class Request extends Component
{
    // HTTP Methods
    final public const GET = 'GET';
    final public const POST = 'POST';
    final public const PUT = 'PUT';
    final public const DELETE = 'DELETE';
    final public const HEAD = 'HEAD';
    final public const OPTIONS = 'OPTIONS';

    protected ?string $_method = null;

    final public const SAME_SITE_LAX = 'Lax';
    final public const SAME_SITE_STRICT = 'Strict';

    /**
     * @var  string  the URI of the request
     */
    protected string $_uri;

    protected ?string $_hostname = null;

    public bool $cookie_validation = false;

    public bool $enable_csrf_cookie = true;

    public string $csrf_token_name = 'csrf_token';

    protected ?string $_csrf_token = null;

    /**
     * @var  string  Magic salt to add to the cookie
     */
    protected string $cookie_salt;

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
    public bool $cookie_secure = false; // todo: true by default?

    /**
     * @var  boolean  Only transmit cookies over HTTP, disabling Javascript access
     */
    public bool $cookie_httponly = true;

    public string $cookie_samesite = self::SAME_SITE_LAX;

    /**
     * @var  array parameters from the route
     */
    public array $params = [];


    public function init(array $config = []): void
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }

      /*  if ($this->_method === 'PUT') {
            \parse_str($this->rawBody(), $_POST);
        }*/
    }


    public function setUri(string $uri, ?string $baseUrl = null): bool
    {
        $uri = \parse_url($uri, \PHP_URL_PATH);

        if($uri === null || $uri === false) {
            return false;
        }

        if(empty($uri)) {
            $uri = '/';
        }

        if (!\is_null($baseUrl) && \str_starts_with($uri, $baseUrl)) {
            // Remove the base URL from the URI
            $uri = \substr($uri, \strlen($baseUrl));
        }

        $this->_uri = $uri;

        return true;
    }


    public function uri(): string
    {
        return $this->_uri;
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


    public function setMethod(string $method): void
    {
        $this->_method = \strtoupper($method);
    }


    /**
     * Gets or sets the HTTP method. Usually GET, POST, PUT or DELETE in
     * traditional CRUD applications.
     *
     * @return  mixed
     */
    public function method(): string
    {
        if ($this->_method === null) {
            $this->setMethod($_SERVER['REQUEST_METHOD'] ?? 'GET');
        }

        return $this->_method;
    }

    /**
     * Return if the request is sent via secure channel (https).
     * @return boolean if the request is sent via secure channel (https)
     */
    public function isSecure(): bool
    {
        return (isset($_SERVER['HTTPS']) && (\strcasecmp($_SERVER['HTTPS'], 'on') === 0 || $_SERVER['HTTPS'] == 1))
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && \strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0);
    }


    /**
     *  Returns GET parameter with a given name. If name isn't specified, returns an array of all GET parameters.
     *
     * @param null $key
     * @param null $default
     */
    public function get($key = null, mixed $default = null): mixed
    {
        if ($key) {
            return $_GET[$key] ?? $default;
        }

        return $_GET;
    }

    /**
     * Gets HTTP POST parameters of the request.
     *
     * @param mixed  $key Parameter name
     * @param string|null $default Default value if parameter does not exist
     */
    public function post(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }

        return $_POST[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function input(string $name, mixed $default = null): mixed
    {
        return $_POST[$name] ?? $_GET[$name] ?? $default;
    }

    public function csrfToken(bool $new = false): string
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

    public function loadCsrfToken(): ?string
    {
        if ($this->enable_csrf_cookie) {
            return $this->getCookie($this->csrf_token_name);
        }

        return \Mii::$app->session->get($this->csrf_token_name);
    }


    /**
     * Returns whether this is an AJAX (XMLHttpRequest) request.
     * TODO: do we still need this?
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


    private ?string $_raw_body = null;

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
     */
    public function getCookie(string $key, mixed $default = null): mixed
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
     */
    public function setCookie(string $name, string $value, ?int $expiration = null): bool
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
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function salt(string $name, string $value): string
    {
        // Require a valid salt
        if (!$this->cookie_salt) {
            throw new \InvalidArgumentException(
                'A valid cookie salt is required. Please set components.request.cookie_salt.'
            );
        }
        return \substr(\mii\util\Text::b64Encode(\md5($name . $value . $this->cookie_salt, true)), 0, 20);
    }
}
