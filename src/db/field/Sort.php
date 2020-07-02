<?php declare(strict_types=1);

namespace mii\db\field;

use mii\db\DB;
use mii\db\ORM;
use mii\db\SelectQuery;

class Sort
{

    /**
     * @var \mii\db\ORM
     */
    protected $_model;
    protected $_field;

    public function __construct(?ORM $model = null, ?string $field_name = null)
    {
        $this->_model = $model;
        $this->_field = $field_name;
    }


    public function value(?string $parent_field = null)
    {
        if ($parent_field) {
            $value = (new SelectQuery)
                ->select([DB::expr('MAX(' . $this->_field . ')'), $this->_field])
                ->from($this->_model::table())
                ->where($parent_field, '=', $this->_model->get($parent_field))
                ->one();

            if ($value) {
                $value = $value[$this->_field] ?: 0;
            }
        } else {
            $value = (new SelectQuery)
                ->select([DB::expr('MAX(' . $this->_field . ')'), $this->_field])
                ->from($this->_model::table())
                ->one();

            if ($value) {
                $value = $value[$this->_field] ?: 0;
            }
        }

        return $value + 1;
    }
}
