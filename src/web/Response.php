<?php declare(strict_types=1);

namespace mii\web;

use mii\core\Component;

class Response extends Component
{
    final public const FORMAT_RAW = 0;
    final public const FORMAT_HTML = 1;
    final public const FORMAT_JSON = 2;
    final public const FORMAT_XML = 3;

    public int $format = self::FORMAT_HTML;

    /**
     * @var  integer     The response http status
     */
    public int $status = 200;

    public string $status_message = '';

    /**
     * @var  string      The response body
     */
    protected string $_content = '';

    protected array $_headers = [];


    public function clear(): void
    {
        $this->_content = '';
        $this->_headers = [];
        $this->status = 200;
        $this->status_message = '';
    }

    /**
     * Adds a new header.
     * If there is already a header with the same name, it will be replaced.
     * @param string $name the name of the header
     * @param string|array $value the value of the header
     * @return static object itself
     */
    public function setHeader(string $name, string|array $value = ''): static
    {
        $name = \strtolower($name);
        $this->_headers[$name] = (array) $value;
        return $this;
    }

    /**
     * Adds a new header.
     * If there is already a header with the same name, the new one will
     * be appended to it instead of replacing it.
     * @param string $name the name of the header
     * @param string $value the value of the header
     */
    public function addHeader(string $name, string $value): static
    {
        $name = \strtolower($name);
        $this->_headers[$name][] = $value;
        return $this;
    }


    public function removeHeader(string $name): static
    {
        $name = \strtolower($name);
        if (isset($this->_headers[$name])) {
            unset($this->_headers[$name]);
        }
        return $this;
    }


    public function sendHeaders()
    {
        \assert(\headers_sent() === false, 'Headers were already sent');

        if (!isset($this->_headers['content-type'])) {
            $this->setHeader('content-type', 'text/html; charset=UTF-8');
        }

        // Create the response header
        if ($this->status !== 200) {
            if (empty($this->status_message)) {
                \http_response_code($this->status);
            } else {
                \header("HTTP/1.1 $this->status $this->status_message");
            }
        }


        if ($this->_headers) {
            foreach ($this->_headers as $name => $values) {
                $name = \str_replace(' ', '-', \ucwords(\str_replace('-', ' ', $name)));
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
     * @param int|null $status Status to set to this response
     * @return  mixed
     * @noinspection PhpInconsistentReturnPointsInspection
     */
    public function status(int $status = null)
    {
        if ($status === null) {
            return $this->status;
        }
        $this->status = $status;
    }


    public function redirect(string $url, int $code = 302): static
    {
        // todo: process url

        if (\Mii::$app->request->isAjax()) {
            $this->setHeader('X-Redirect', $url);
        } else {
            $this->setHeader('Location', $url);
        }

        $this->status($code);

        return $this;
    }


    public function content($content = null)
    {
        if ($content === null) {
            return $this->_content;
        }

        $this->_content = $content;

        return null;
    }


    public function send()
    {
        switch ($this->format) {
            case self::FORMAT_HTML:
                $this->setHeader('content-type', 'text/html; charset=UTF-8');
                break;
            case self::FORMAT_JSON:
                $this->setHeader('content-type', 'application/json; charset=UTF-8');
                $this->_content = \json_encode($this->_content, JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
                break;
            case self::FORMAT_XML:
                $this->setHeader('content-type', 'application/xml; charset=UTF-8');
                break;
        }

        $this->sendHeaders();
        $this->sendContent();

        // todo: fastcgi_finish_request();
    }


    protected function sendContent()
    {
        echo $this->_content;
    }
}
