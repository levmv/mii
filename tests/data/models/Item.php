<?php declare(strict_types=1);

namespace miit\data\models;

use mii\db\ORM;

class Item extends ORM
{
    public function onCreate(): void
    {
        $this->created = \time();
    }
}
