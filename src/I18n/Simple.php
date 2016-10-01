<?php
namespace mii\I18n;


use mii\web\Exception;

class Simple {

    protected $language;

    protected $base_path;

    protected $messages;

    public function __construct($config = []) {
        foreach($config as $key => $value) {
            $this->$key = $value;
        }

        if($this->base_path) {
            $this->base_path = \Mii::resolve($this->base_path);
        } else {
            $this->base_path = path('app').'/messages';
        }

        if($this->language === null) {
            $this->language = \Mii::$app->language;
        }

    }

    private function load() {

        if(!is_file($this->base_path.'/'.$this->language.'.php')) {
            throw new Exception();
        }

        $this->messages = require($this->base_path.'/'.$this->language.'.php');
    }


    public function translate($string) {
        if($this->messages === null)
            $this->load();


        return isset($this->messages[$string]) ? $this->messages[$string] : $string;
    }

}

