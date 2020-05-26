<?php

namespace mii\web;

use mii\core\ErrorException;
use mii\core\InvalidRouteException;
use mii\core\UserException;
use mii\db\ModelNotFoundException;
use mii\util\Debug;

class ErrorHandler extends \mii\core\ErrorHandler
{

    public $route;

    public function render($exception) {

        if (\Mii::$app->has('response')) {
            $response = \Mii::$app->response;
            $response->clear();
        } else {
            $response = new Response();
        }

        if ($exception instanceof HttpException) {
            $response->status($exception->status_code);
        } else if(
            $exception instanceof InvalidRouteException ||
            $exception instanceof ModelNotFoundException) {

            $response->status(404);

        } else {
            $response->status(500);
        }

        if($response->format === Response::FORMAT_HTML) {

            if($this->route && !\config('debug')) {
                \Mii::$app->request->uri($this->route);
                try {
                    \Mii::$app->run();
                    return;
                } catch (\Throwable $t) {
                    $response->content(static::exception_to_text($exception));
                }
            } else {
                if(config('debug')) {
                    $params = [
                        'class' => $exception instanceof ErrorException ? $exception->get_name() : \get_class($exception),
                        'code' => $exception->getCode(),
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'trace' => $exception->getTrace()
                    ];
                    /*if($exception instanceof ModelNotFoundException) {
                        array_shift($params['trace']);
                        $params['file'] = $params['trace'][0]['file'];
                        $params['line'] = $params['trace'][0]['line'];
                    }*/

                    $response->content($this->render_file(__DIR__ . '/Exception/error.php', $params));
                } else {
                    $response->content('<pre>' . e(static::exception_to_text($exception)) . '</pre>');
                }
            }

        } elseif ($response->format === Response::FORMAT_JSON) {
            $response->content($this->exception_to_array($exception));
        } else {
            $response->content(static::exception_to_text($exception));
        }

        $response->send();
    }


    public function render_file($__file, $__params) {
        $__params['handler'] = $this;
        ob_start();
        ob_implicit_flush(false);
        extract($__params, EXTR_OVERWRITE);
        require($__file);
        return ob_get_clean();
    }

    protected function exception_to_array($e) {

        $arr = [
            'name' => 'Exception',
            'message' => 'An internal server error occurred.',
            'code' => $e->getCode()
        ];

        if ($e instanceof UserException || config('debug')) {
            $arr['name'] = \get_class($e);
            $arr['message'] = $e->getMessage();
        }

        if ($e instanceof HttpException) {
            $arr['status'] = $e->status_code;
        }

        if (config('debug')) {
            $arr['type'] = \get_class($e);
            $arr['file'] = Debug::path($e->getFile());
            $arr['line'] = $e->getLine();
        }
        if (($prev = $e->getPrevious()) !== null) {
            $arr['previous'] = $this->exception_to_array($prev);
        }
        return $arr;
    }

}
