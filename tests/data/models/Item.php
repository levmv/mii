<?php

namespace miit\data\models;

use mii\db\ORM;

class Item extends ORM {


    public function onCreate()
    {
        $this->created = time();
    }

}
