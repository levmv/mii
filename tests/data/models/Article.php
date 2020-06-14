<?php

namespace miit\data\models;

use mii\db\JsonAttributes;
use mii\db\ORM;

class Article extends ORM {

    use JsonAttributes;

    protected static array $json_attributes = ['data'];

}
