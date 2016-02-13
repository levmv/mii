<?php

namespace mii\web;

class ForbiddenHttpException extends HttpException {

    public function __construct($message = null, $variables = []) {
        parent::__construct(403, $message, $variables);
    }

};