<?php declare(strict_types=1);

namespace mii\web;

use Mii;

class BadRequestHttpException extends HttpException
{
    public ?array $validateErrors = null;

    public function __construct($message = null)
    {
        if ($message === null) { // TODO: do we need this?
            $message = 'Bad request to ' . Mii::$app->request->uri();
        }
        parent::__construct(400, $message);
    }
}
