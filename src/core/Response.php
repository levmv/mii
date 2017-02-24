<?php

namespace mii\core;

class Response {


    public function __construct($config = []) {
        foreach($config as $key => $value)
            $this->$key = $value;

    }
}
