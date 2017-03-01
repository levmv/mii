<?php

namespace mii\console;


class Response {

    public $exit_status = 0;


    public function __construct($config = []) {
        foreach($config as $key => $value)
            $this->$key = $value;

    }

    public function send() {

    }

}