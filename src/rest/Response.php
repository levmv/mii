<?php

namespace mii\rest;


class Response {

    public $headers = [];

    public $content;

    protected $_uri;

    protected $_info;

    protected $_error;

    protected $_result;

    /**
     * Sets the total number of rows and stores the result locally.
     *
     * @param   mixed $result query result
     * @param   string $sql SQL query
     * @param   mixed $as_object
     * @param   array $params
     * @return  void
     */
    public function __construct($response, $url, $info, $error) {
        // Store the result locally
        $this->_result = $response;

        $this->_uri = $url;

        $this->_info = $info;

        $this->_error = $error;

        $response_status_lines = [];
        $line = strtok($response, "\n");

        do {
            if(\strlen(trim($line)) == 0){
                // Since we tokenize on \n, use the remaining \r to detect empty lines.
                if(\count($this->headers) > 0) break; // Must be the newline after headers, move on to response body
            }
            elseif(strpos($line, 'HTTP') === 0){
                // One or more HTTP status lines
                $response_status_lines[] = trim($line);
            }
            else {
                // Has to be a header
                list($key, $value) = explode(':', $line, 2);
                $key = trim(strtolower(str_replace('-', '_', $key)));
                $value = trim($value);

                if(empty($this->headers[$key]))
                    $this->headers[$key] = $value;
                elseif(\is_array($this->headers[$key]))
                    $this->headers[$key][] = $value;
                else
                    $this->headers[$key] = [$this->headers[$key], $value];
            }
        } while($line = strtok("\n"));

        // TODO:
        $this->content = json_decode(strtok(""), true);
    }

    public function get(string $name, $default = null) {
        return $this->content[$name] ?? $default;
    }

    public function iterate($name = null) {
        if($name === null) {
            foreach($this->content as $value)
                yield $value;
        } else {
            if(!isset($this->content[$name]) || \is_array($this->content))
                return;

            foreach($this->content[$name] as $value)
                yield $value;
        }
    }

    public function status_code() : int {
        return (int) $this->_info['http_code'];
    }


}
