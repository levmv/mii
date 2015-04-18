<?php

namespace mii\db\field;


use mii\db\DB;
use mii\db\Query;

class Sort {

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



    public function value($parent_field = false) {

        if($parent_field) {

            $value = (new Query)
                ->select([
                    [DB::expr('MAX('.$this->_field.')'),  $this->_field]
                ])
                ->from($this->_model->get_table())
                ->where($parent_field, '=', $this->_model->get($parent_field))
                ->one();

            if($value) {
                $value = $value[$this->_field] ?: 0;
            }
            echo $value;exit;


        } else {

            $value = (new Query)
                ->select([
                    [DB::expr('MAX('.$this->_field.')'), $this->_field]
                ])
                ->from($this->_model->get_table())
                ->one();

            if($value) {
                $value = $value[$this->_field] ?: 0;
            }

        }

        return $value+1;
    }


}