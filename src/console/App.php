<?php

namespace mii\console;

use Mii;

class App extends \mii\core\App
{

    protected $_blocks;

    protected $_session;


    public function run()
    {
        $loggers = $this->config('log');
        if($loggers) {

            foreach($loggers as $class_name => $log_params) {
                Mii::add_logger(new $class_name($log_params));
            }

        }

        try {
            $this->request = new Request();

            $this->request->execute()->send();

        } catch (\Exception $e) {
            throw $e;
        }
    }


    public function register_exception_handler()
    {
        set_exception_handler(['\mii\console\CliException', 'handler']);
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