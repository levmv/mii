<?php /** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
declare(strict_types=1);

namespace mii\web;

use mii\core\InvalidRouteException;
use mii\core\UserException;
use mii\db\ModelNotFoundException;
use mii\util\Debug;

class ErrorHandler extends \mii\core\ErrorHandler
{
    public ?string $route = null;

    public function prepareException(\Throwable $e): \Throwable
    {
        if (config('debug')) {
            return $e;
        }

        if ($e instanceof ModelNotFoundException) {
            return new NotFoundHttpException($e->getMessage(), $e);
        }

        if ($e instanceof InvalidRouteException) {
            return new NotFoundHttpException($e->getMessage(), $e);
        }

        return $e;
    }

    public function render($exception): void
    {
        if (\Mii::$app->has('response')) {
            $response = \Mii::$app->response;
            $response->clear();
        } else {
            $response = new Response();
        }

        if ($exception instanceof HttpException) {
            $response->status($exception->getStatusCode());
        } else {
            $response->status(500);
        }

        if ($response->format === Response::FORMAT_HTML) {
            if ($this->route && !\config('debug')) {
                try {
                    \Mii::$app->run($this->route);
                    return;
                } catch (\Throwable) {
                    $response->content(static::exceptionToText($exception));
                }
            } elseif (config('debug')) {
                $response->content($this->renderFile(__DIR__ . '/Exception/error.php', ['exception' => $exception]));
            } else {
                $response->content('<pre>' . e(static::exceptionToText($exception)) . '</pre>');
            }
        } elseif ($response->format === Response::FORMAT_JSON) {
            $response->content($this->exceptionToArray($exception));
        } else {
            $response->content(static::exceptionToText($exception));
        }

        $response->send();
    }


    public function renderFile($__file, $__params)
    {
        $__params['handler'] = $this;
        \ob_start();
        //\ob_implicit_flush(false);
        \extract($__params, \EXTR_OVERWRITE);
        require $__file;
        return \ob_get_clean();
    }

    protected function exceptionToArray($e)
    {
        $arr = [
            'name' => 'Exception',
            'message' => 'An internal server error occurred.',
            'code' => $e->getCode(),
        ];

        if ($e instanceof UserException || config('debug')) {
            $arr['name'] = $e::class;
            $arr['message'] = $e->getMessage();
        }

        if ($e instanceof HttpException) {
            $arr['status'] = $e->getStatusCode();
        }

        if (config('debug')) {
            $arr['type'] = $e::class;
            $arr['file'] = Debug::path($e->getFile());
            $arr['line'] = $e->getLine();
        }
        if (config('debug') && ($prev = $e->getPrevious()) !== null) {
            $arr['previous'] = $this->exceptionToArray($prev);
        }
        return $arr;
    }
}
