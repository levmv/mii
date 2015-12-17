<?php

namespace mii\core;


class Router {

    private $_routes;

    public function __construct($config = []) {
        foreach($config as $key => $value)
            $this->$key = $value;
    }





    public function match($uri) {

    }


}