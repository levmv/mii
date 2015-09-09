<?php

namespace mii\web;


use mii\util\Arr;
use mii\util\HTML;
use mii\util\Upload;
use mii\valid\Validation;
use mii\web\Request;

class Form {

    /**
     * @var Validation
     */
    public $validation;

    public $fields = [];

    public $select_data = [];

    public $labels = [];

    /**
     * @param null $data Initial data for form. If null then request->post() will be used
     */
    public function __construct($data = null) {

        $this->validation = new Validation();
    }

    /**
     * Return true if request method is post
     * @return bool
     */
    public function load($data = null) {


        $data = !empty($data) ? $data : \Mii::$app->request->post();

        if(count($data)) {
            $this->fields = Arr::overwrite($this->fields, $data);

        }

        return $this->posted();
    }

    public function posted() {
        return \Mii::$app->request->method() === Request::POST;
    }


    public function rules() {
        return [];
    }

    public function fields() {
        return $this->fields;
    }

    public function get($name) {
        return $this->fields[$name];
    }

    public function set($name, $value) {
        return $this->fields[$name] =  $value;
    }


    public function validate() {

        $this->validation->rules($this->rules());

        $this->validation->data(\Mii::$app->request->post());

        return $this->validation->check();
    }

    public function errors() {
        return $this->validation->errors();
    }


    public function open($action = NULL, $attributes = NULL) {
        return HTML::open($action, $attributes);
    }

    public function close() {
        return HTML::close();
    }

    public function input($name, $attributes = null) {
        return $this->field('input', $name, $attributes);
    }

    public function textarea($name, $attributes = null) {
        return $this->field('textarea', $name, $attributes);
    }

    public function checkbox($name, $attributes = null) {
        return $this->field('checkbox', $name, $attributes);
    }

    public function hidden($name, $attributes = null) {
        return $this->field('hidden', $name, $attributes);
    }

    public function file($name, $attributes = null) {
        return $this->field('file', $name, $attributes);
    }


    public function select($name, $attributes = null) {
        return $this->field('select', $name, $attributes);
    }

    public function password($name, $attributes = null) {
        return $this->field('password', $name, $attributes);
    }

    public function field($type, $name, $attributes = null) {

        if(!array_key_exists($name, $this->fields)) {
            $this->fields[$name] = null;
        }

        switch($type) {
            case 'input':
                return HTML::input($name, $this->fields[$name], $attributes);
            case 'hidden':
                return HTML::hidden($name, $this->fields[$name], $attributes);
            case 'textarea':
                return HTML::textarea($name, $this->fields[$name], $attributes);
            case 'checkbox':
                return HTML::checkbox($name, 1, (bool) $this->fields[$name], $attributes);
            case 'password':
                return HTML::password($name, $this->fields[$name], $attributes);
            case 'select':
                if($attributes AND isset($attributes['multiple']) AND $attributes['multiple'] !== false) {
                    return HTML::select($name.'[]', $this->select_data[$name], $this->fields[$name], $attributes);
                }
                return HTML::select($name, $this->select_data[$name], $this->fields[$name], $attributes);
            case 'file':
                return HTML::file($name, $attributes);
        }
    }

    public function label($field_name, $label_name, $attributes = null) {
        $this->labels[$field_name] = $label_name;

        return HTML::label($field_name, $label_name, $attributes);
    }

    public function uploaded($name) {
        
        return isset($_FILES[$name]) AND Upload::not_empty($_FILES[$name]) AND Upload::valid($_FILES[$name]);

    }



}
