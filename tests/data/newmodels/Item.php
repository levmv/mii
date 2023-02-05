<?php declare(strict_types=1);

namespace miit\data\newmodels;

use mii\db\Model;

class Item extends Model
{
    protected int $id;
    protected string $name = '';
    protected ?string $some = null;
    protected int $created;
    protected int $updated = 0;

    public function onCreate(): void
    {
        $this->created = time();
    }
}
