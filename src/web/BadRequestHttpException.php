<?php

namespace mii\web;

use Mii;

class BadRequestHttpException extends HttpException
{

    public function __construct($message = null) {
        if ($message === null) {
            $message = "Bad request to ".Mii::$app->request->uri();
        }
        parent::__construct(400, $message);
    }

}

;