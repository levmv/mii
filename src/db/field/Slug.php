<?php

namespace mii\db\field;


use mii\util\UTF8;

class Slug {


    public $separator = '-';

    /**
     * @var \mii\db\ORM
     */
    protected $_model;
    protected $_field;

    public function __construct($model = NULL, $field_name = NULL)
    {
        $this->_model = $model;
        $this->_field = $field_name;
    }


    public function value() {

        // Transliterate russian to latin
        $value = UTF8::ru_translit($this->_model->get($this->_field));

        // Transliterate value to ASCII
        $value = UTF8::transliterate_to_ascii($value);

        // Set preserved characters
        $preserved_characters = preg_quote($this->separator);

        // Remove all characters that are not in preserved characters, a-z, 0-9, point or whitespace
        $value = preg_replace('![^'.$preserved_characters.'a-z0-9.\s]+!', '', strtolower($value));

        // Replace all separator characters and whitespace by a single separator
        $value = preg_replace('!['.preg_quote($this->separator).'\s]+!u', $this->separator, $value);

        // Trim separators from the beginning and end
        $value = trim($value, $this->separator);


        return $value;
    }


}