<?php

namespace mii\core;


abstract class Request {

    /**
     * @var  array   parameters from the route
     */
    public $params = [];

    /**
     * @var  string  controller to be executed
     */
    public $controller;

    /**
     * @var  string  action to be executed in the controller
     */
    public $action;


    public function __construct($config = []) {
        foreach($config as $key => $value)
            $this->$key = $value;

        $this->init();
    }

    abstract function execute();

}