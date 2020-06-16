<?php declare(strict_types=1);

namespace mii\web;

class ForbiddenHttpException extends HttpException
{

    public function __construct($message = null)
    {
        if ($message === null) {
            $message = "Access to " . \Mii::$app->request->uri() . " denied";
        }
        parent::__construct(403, $message);
    }

}
