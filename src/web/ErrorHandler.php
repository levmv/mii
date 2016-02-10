<?php

namespace mii\web;

use mii\core\ErrorException;
use mii\core\Exception;
use mii\core\UserException;

class ErrorHandler extends \mii\core\ErrorHandler {

    public $route;

    public function render($exception) {

        if(\Mii::$app->has('response')) {
            $response = \Mii::$app->response;
        } else {
            $response = new Response();
        }

        if($this->route && $response->format === Response::FORMAT_HTML && !config('debug')) {

            // todo

        } elseif( $response->format === Response::FORMAT_HTML) {

            if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {

                $response->content('<pre>' . e(Exception::text($exception)) . '</pre>');

            } else {

                $params = [
                    'class' => get_class($exception),
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTrace()
                ];
                $response->content($this->render_file(__DIR__ . '/Exception/error.php', $params));
            }

        } else {
            $response->content(Exception::text($exception));
        }


        if ($exception instanceof HttpException) {
            $response->status($exception->status_code);
        } else {
            $response->status(500);
        }

        $response->send();

    }

    public function render_file($__file, $__params)
    {
        $__params['handler'] = $this;
        ob_start();
        ob_implicit_flush(false);
        extract($__params, EXTR_OVERWRITE);
        require($__file);
        return ob_get_clean();
    }

    protected function exception_to_array($e) {
        if (!config('debug') && !$e instanceof HttpException) {
            $e = new HttpException(500, 'There was an error at the server.');
        }
        $array = [
            'name' => ($e instanceof Exception || $e instanceof ErrorException) ? $e->get_name() : 'Exception',
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ];
        if ($e instanceof HttpException) {
            $array['status'] = $e->statusCode;
        }
        if (config('debug')) {
            $array['type'] = get_class($e);
            $array['file'] = $e->getFile();
            $array['line'] = $e->getLine();
            $array['stack-trace'] = explode("\n", $e->getTraceAsString());
        }
        if (($prev = $e->getPrevious()) !== null) {
            $array['previous'] = $this->exception_to_array($prev);
        }
        return $array;
    }

}
