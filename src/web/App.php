<?php

namespace mii\web;

use Mii;

class App extends \mii\core\App
{

    public $user;

    protected $_blocks;

    protected $_session;


    public function run()
    {

        if($loggers = $this->config('log')) {

            foreach($loggers as $class_name => $log_params) {
                Mii::add_logger(new $class_name($log_params));
            }

        }

        $cookie_config = $this->config('cookie');

        foreach($cookie_config as $key => $value) {
            Cookie::$$key = $value;
        }

        /*if (isset($cookie_config['salt']))
            Cookie::$salt = $cookie_config['salt'];

        if (isset($cookie_config['domain']))
            Cookie::$salt = $cookie_config['domain'];*/

        $this->blocks(new Blocks($this->config('blocks')));

        try {
            $this->request = new Request();

            $this->request->execute()->send();

        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function blocks(Blocks $blocks = null)
    {
        if ($blocks === null)
            return $this->_blocks;

        $this->_blocks = $blocks;
    }


    public function auth() {
        if ($this->_auth === null)
        {
            $config = $this->config('auth');

            $this->_auth = new \mii\auth\Auth($config);
        }

        return $this->_auth;
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


}