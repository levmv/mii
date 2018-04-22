<?php

namespace mii\storage;


abstract class Storage {

    protected $path = '';
    protected $url = '';


    public function __construct($config)
    {
        $this->init($config);
        $this->path = \Mii::resolve($this->path);
    }


    public function init($config) {
        foreach($config as $key => $value)
            $this->$key = $value;
    }


    protected function resolve(string $path) : string {
        return $this->path.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
    }


}