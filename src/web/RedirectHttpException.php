<?php

namespace mii\web;

class RedirectHttpException extends Exception {

    public $url = '';

    public function __construct($url) {
        $this->url = $url;
    }

};