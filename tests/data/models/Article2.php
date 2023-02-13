<?php declare(strict_types=1);

namespace miit\data\models;

use mii\db\ORM;

/**
 * @property array $data
 * @property bool  $flag
 */
class Article2 extends ORM
{
    protected array $casts = [
        'data' => 'array',
        'flag' => 'bool',
        'deleted' => 'int'
    ];

    public static function table(): string
    {
        return 'articles';
    }
}
