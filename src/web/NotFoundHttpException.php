<?php

namespace mii\web;

use Mii;

class NotFoundHttpException extends HttpException
{

    public function __construct() {
        parent::__construct(404, Mii::$app->request->uri());
    }

    public static function text(\Throwable $e) {
        return (new \ReflectionClass($e))->getShortName() .' : '.$e->getMessage();
    }

}