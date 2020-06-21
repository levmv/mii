<?php declare(strict_types=1);

namespace mii\web;

use Mii;

class NotFoundHttpException extends HttpException
{
    public function __construct($message = '', \Exception $previous = null)
    {
        $uri = Mii::$app->request->uri();
        $message = $message ? $message . " [URI:$uri]" : $uri;

        parent::__construct(404, $message, 0, $previous);
    }

    public static function text(\Throwable $e): string
    {
        return (new \ReflectionClass($e))->getShortName() . ' : ' . $e->getMessage();
    }
}
