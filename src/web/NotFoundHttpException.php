<?php

namespace mii\web;

use Mii;

class NotFoundHttpException extends HttpException
{

    public function __construct() {
        parent::__construct(404, "Page not found ". Mii::$app->request->uri());
    }

}

;