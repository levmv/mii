<?php

namespace mii\web;

class HttpException extends Exception {

    public function __construct($code = 0, $message = "", array $variables = NULL) {
        parent::__construct($message, $variables, $code);
    }

};