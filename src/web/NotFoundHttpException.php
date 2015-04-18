<?php

namespace mii\web;

use Mii;

class NotFoundHttpException extends Exception {

    public function __construct() {
        parent::__construct("Page not found :page", [':page' => Mii::$app->request->uri()], 404);
    }

};