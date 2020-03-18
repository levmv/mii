<?php

namespace mii\web;


use mii\core\Component;

class Response extends Component
{
    const FORMAT_RAW = 0;
    const FORMAT_HTML = 1;
    const FORMAT_JSON = 2;
    const FORMAT_XML = 3;

    public int $format = self::FORMAT_HTML;

    /**
     * @var  integer     The response http status
     */
    public int $status = 200;

    public string $status_message = '';

    /**
     * @var  string      The response body
     */
    protected $_content = '';

    protected array $_headers = [];


    /**
     * Adds a new header.
     * If there is already a header with the same name, it will be replaced.
     * @param string $name the name of the header
     * @param string $value the value of the header
     * @return static object itself
     */
    public function set_header($name, $value = '')
    {
        $name = \strtolower($name);
        $this->_headers[$name] = (array)$value;
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
        $name = \strtolower($name);
        $this->_headers[$name][] = $value;
        return $this;
    }


    public function remove_header($name)
    {
        $name = \strtolower($name);
        if (isset($this->_headers[$name]))
            unset($this->_headers[$name]);
        return $this;
    }


    public function send_headers()
    {
        assert(headers_sent() === false, "Headers were already sent");

        if (!isset($this->_headers['content-type'])) {
            $this->set_header('content-type', 'text/html; charset=UTF-8');
        }

        // Create the response header
        if ($this->status !== 200) {
            if (empty($this->status_message)) {
                \http_response_code($this->status);
            } else {
                \header("HTTP/1.1 {$this->status} {$this->status_message}");
            }
        }


        if ($this->_headers) {
            foreach ($this->_headers as $name => $values) {
                $name = \str_replace(' ', '-', ucwords(\str_replace('-', ' ', $name)));
                // set replace for first occurrence of header but false afterwards to allow multiple
                $replace = true;
                foreach ($values as $value) {
                    \header("$name: $value", $replace);
                    $replace = false;
                }
            }
        }

        return $this;
    }


    /**
     * Sets or gets the HTTP status from this response.
     *
     * @param integer $status Status to set to this response
     * @return  mixed
     */
    public function status(int $status = NULL)
    {
        if ($status === NULL) {
            return $this->status;
        }
        $this->status = $status;
    }


    public function redirect($url, $code = 302)
    {
        // todo: process url

        if (\Mii::$app->request->is_ajax()) {
            $this->set_header('X-Redirect', $url);
        } else {
            $this->set_header('Location', $url);
        }

        $this->status($code);

        return $this;
    }


    public function content($content = null)
    {
        if ($content === null)
            return $this->_content;

        $this->_content = $content;

        return null;
    }


    public function send()
    {
        switch ($this->format) {
            case self::FORMAT_HTML:
                $this->set_header('content-type', 'text/html; charset=UTF-8');
                break;
            case self::FORMAT_JSON:
                $this->set_header('content-type', 'application/json; charset=UTF-8');
                $this->_content = \json_encode($this->_content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;
            case self::FORMAT_XML:
                $this->set_header('content-type', 'application/xml; charset=UTF-8');
                break;
        }

        $this->send_headers();
        $this->send_content();

        // todo: fastcgi_finish_request();
    }


    protected function send_content()
    {
        echo $this->_content;
    }
}