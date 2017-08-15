<?php

namespace mii\web;

use Mii;

class BadRequestHttpException extends HttpException
{

    public function __construct($message = null, $variables = []) {
        if ($message === null) {
            $message = "Bad request to :page";
            $variables = [':page' => Mii::$app->request->uri()];
        }
        parent::__construct(400, $message, $variables);
    }

}

;