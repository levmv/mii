<?php

namespace mii\core;

use Mii;


abstract class App {


    public $paths = [];

    /**
     * @var \mii\web\Request|\mii\console\Request;
     */
    public $request = null;

    public $_config = [];

    protected $_auth;


    public function __construct(array $config = []) {

        Mii::$app = $this;

        $this->init($config);
    }

    public function init(array $config) {

        $this->_config = $config;

        if(isset($this->_config['paths'])) {
            $this->paths = $this->_config['paths'];
        }

        if(isset($this->_config['log'])) {
            foreach($this->_config['log'] as $log_class => $log_config) {

                $logger = new $log_class($log_config);
            }
        }


        $this->register_exception_handler();
        $this->register_error_handler();

        register_shutdown_function(function() {
            if ($error = error_get_last() AND in_array($error['type'], [E_PARSE, E_ERROR, E_USER_ERROR]))
            {
                // Clean the output buffer
                ob_get_level() AND ob_clean();

                // Fake an exception for nice debugging
                //throw new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']);
                \mii\web\Exception::handler(new \ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']));

                // Shutdown now to avoid a "death loop"
                exit(1);
            }
        });
    }


    public function config($group = false, $value = false) {
        if($value) {
            if($group)
                $this->_config[$group] = $value;
            else
                $this->_config = $value;

        } else {

            if ($group) {
                if(isset($this->_config[$group]))
                    return $this->_config[$group];
                return [];
            }

            return $this->_config;
        }
    }


    public function path($name) {
        return $this->paths[$name];
    }

    public function get_user_class() {

        $config = $this->config('auth');
        return (isset($config['user_model'])) ? $config['user_model'] : 'app\models\User';
    }


}