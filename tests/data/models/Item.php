<?php

namespace miit\data\models;

use mii\db\ORM;

class Item extends ORM {


    public function on_create()
    {
        $this->created = time();
    }

}
