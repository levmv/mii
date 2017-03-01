<?php

namespace mii\web;


class Response {

    public $exit_status = 0;

    // HTTP status codes and messages
    public static $messages = [
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',

        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',

        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found', // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',

        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        507 => 'Insufficient Storage',
        509 => 'Bandwidth Limit Exceeded'
    ];


    const FORMAT_RAW = 'raw';
    const FORMAT_HTML = 'html';
    const FORMAT_JSON = 'json';
    const FORMAT_JSONP = 'jsonp';
    const FORMAT_XML = 'xml';

    public $format = self::FORMAT_HTML;

    /**
     * @var  integer     The response http status
     */
    protected $_status = 200;

    /**
     * @var  string      The response body
     */
    protected $_content = '';

    /**
     * @var  string      The response protocol
     */
    protected $_protocol;


    protected $_content_type = 'text/html';


    protected $_headers = [];



    public function __construct($config = []) {
        foreach($config as $key => $value)
            $this->$key = $value;
    }

    /**
     * Adds a new header.
     * If there is already a header with the same name, it will be replaced.
     * @param string $name the name of the header
     * @param string $value the value of the header
     * @return static object itself
     */
    public function set_header($name, $value = '')
    {
        $name = strtolower($name);
        $this->_headers[$name] = (array) $value;
        return $this;
    }

    /**
     * Adds a new header.
     * If there is already a header with the same name, the new one will
     * be appended to it instead of replacing it.
     * @param string $name the name of the header
     * @param string $value the value of the header
     * @return static the collection object itself
     */
    public function add_header($name, $value)
    {
        $name = strtolower($name);
        $this->_headers[$name][] = $value;
        return $this;
    }



    public function send_headers() {
        if (headers_sent()) {
            return $this;
        }
        if(!isset($this->_headers['content-type'])) {
            $this->set_header('content-type', $this->_content_type . '; charset=UTF-8');
        }

        //$statusCode = $this->getStatusCode();
        //header("HTTP/{$this->version} $statusCode {$this->statusText}");

        $protocol = $this->protocol();
        $status = $this->status();

        // Create the response header
        header($protocol.' '.$status.' '.Response::$messages[$status]);

        if ($this->_headers) {
            foreach ($this->_headers as $name => $values) {
                $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
                // set replace for first occurrence of header but false afterwards to allow multiple
                $replace = true;
                foreach ($values as $value) {
                    header("$name: $value", $replace);
                    $replace = false;
                }
            }
        }

        return $this;
    }


    /**
     * Gets or sets the HTTP protocol. The standard protocol to use
     * is `HTTP/1.1`.
     *
     * @param   string   $protocol Protocol to set to the request/response
     * @return  mixed
     */
    public function protocol($protocol = NULL)
    {
        if ($protocol)
        {
            $this->_protocol = strtoupper($protocol);
            return $this;
        }

        if ($this->_protocol === NULL)
        {
            $this->_protocol = 'HTTP/1.1';
        }

        return $this->_protocol;
    }

    /**
     * Sets or gets the HTTP status from this response.
     *
     *      // Set the HTTP status to 404 Not Found
     *      $response = Response::factory()
     *              ->status(404);
     *
     *      // Get the current status
     *      $status = $response->status();
     *
     * @param   integer  $status Status to set to this response
     * @return  mixed
     */
    public function status($status = NULL)
    {

        if ($status === NULL)
        {
            return $this->_status;
        }
        elseif (array_key_exists($status, Response::$messages))
        {
            $this->_status = (int) $status;
            return $this;
        }
        else
        {
            throw new Exception(__METHOD__.' unknown status value : :value', array(':value' => $status));
        }
    }


    public function redirect($url, $code = 302) {

        // todo: process url

        if(\Mii::$app->request->is_ajax()) {
            $this->set_header('X-Redirect', $url);
        } else {
            $this->set_header('Location', $url);
        }

        $this->status($code);

        return $this;
    }



    public function content($content = null) {
        if($content === null)
            return $this->_content;

        $this->_content = $content;
        
        return null;
    }


    public function send() {
        switch($this->format) {
            case self::FORMAT_HTML:
                $this->set_header('content-type','text/html; charset=UTF-8');
                break;
            case self::FORMAT_JSON:
                $this->set_header('content-type','application/json; charset=UTF-8');
                $this->_content = json_encode($this->_content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;
            case self::FORMAT_XML:
                $this->set_header('content-type','application/xml; charset=UTF-8');
                break;


        }

        $this->send_headers();
        $this->send_content();
    }


    protected function send_content() {
        echo $this->_content;
    }
}