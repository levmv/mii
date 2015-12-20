<?php

namespace mii\web;

use Mii;

class App extends \mii\core\App
{

    public $user;

    protected $_blocks;

    public function run()
    {
        $cookie_config = $this->config('cookie');

        foreach($cookie_config as $key => $value) {
            Cookie::$$key = $value;
        }

        try {
            $this->request = $this->get('request');

            $this->request->execute()->send();

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @return Blocks
     * @throws \Exception
     */
    public function blocks()
    {
        return $this->get('blocks');
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
            'response' => ['class' => \mii\web\Response::class]
        ]);
    }



}