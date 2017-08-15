<?php

namespace mii\web;

use Mii;

class NotFoundHttpException extends HttpException
{

    public function __construct() {
        parent::__construct(404, "Page not found :page", [':page' => Mii::$app->request->uri()]);
    }

}

;