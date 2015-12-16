<?php

namespace mii\core;

use Mii;


abstract class App extends ServiceLocator {


    public $charset = 'UTF-8';

    public $controller;

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

        if(isset($this->_config['log'])) {
            foreach($this->_config['log'] as $log_class => $log_config) {

                $logger = new $log_class($log_config);
            }
        }

        $components = $this->default_components();

        if(isset($config['components'])) {
            $components = array_merge($components, $config['components']);
        }

        foreach($components as $name => $config) {
            $this->set($name, $config);
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

    public function register_exception_handler() {}
    public function register_error_handler() {}


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


    public function default_components() {
        return [
            'user' => '\mii\auth\User',
            'auth' => '\mii\auth\Auth',
            'db' => '\mii\db\Database'
        ];
    }

}