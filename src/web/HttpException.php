<?php

namespace mii\web;

class HttpException extends Exception
{

    public $status_code = 500;

    public function __construct($status = 500, $message = "", $code = 0, \Exception $previous = null) {

        $this->status_code = $status;

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string the user-friendly name of this exception
     */
    public function get_name() {
        if (isset(Response::$messages[$this->status_code])) {
            return Response::$messages[$this->status_code];
        } else {
            return 'Error';
        }
    }

}
