<?php

namespace mii\db\field;


use mii\util\Text;

class Slug
{

    /**
     * @var \mii\db\ORM
     */
    protected $_model;
    protected $_field;

    public function __construct($model = NULL, $field_name = NULL) {
        $this->_model = $model;
        $this->_field = $field_name;
    }


    public function value() {
        return Text::to_slug($this->_model->get($this->_field));
    }


}