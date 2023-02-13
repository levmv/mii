<?php declare(strict_types=1);

namespace miit\data\models;

use mii\db\ORM;

/**
 * @property FooEnum $name
 */
class Article4 extends ORM
{
    public static array $casts = [
        'name' => FooEnum::class
    ];

    public static function table(): string
    {
        return 'articles';
    }
}
