<?php

namespace mii\web;

use mii\captcha\Captcha;

/**
 * Class App
 *
 * @property Session $session The session component.
 * @property \mii\auth\User $user The user component.
 * @property Request $request
 * @property Blocks $blocks
 * @property Router $router
 * @property Response $response
 * @property Captcha $captcha
 *
 */
class App extends \mii\core\App
{
    public $user;

    public function run()
    {
        try {
            $this->request->execute()->send();

        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function register_exception_handler()
    {
        set_exception_handler(['\mii\web\Exception', 'handler']);
    }

    public function register_error_handler()
    {

        set_error_handler(function ($code, $error, $file = NULL, $line = NULL) {
            if (error_reporting() & $code) {
                // This error is not suppressed by current error reporting settings
                // Convert the error into an ErrorException
                throw new \ErrorException($error, $code, 0, $file, $line);
            }

            // Do not execute the PHP error handler
            return true;
        });
    }


    public function default_components() {
        return array_merge(parent::default_components(), [
            'session' => ['class' => \mii\web\Session::class],
            'blocks' => ['class' => \mii\web\Blocks::class],
            'router' => ['class' => \mii\web\Router::class ],
            'request' => ['class' => \mii\web\Request::class],
            'response' => ['class' => \mii\web\Response::class],
            'captcha' => ['class' => '\mii\captcha\Captcha']
        ]);
    }



}