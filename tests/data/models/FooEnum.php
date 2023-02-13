<?php declare(strict_types=1);

namespace miit\data\models;

use mii\db\JsonAttributes;
use mii\db\ORM;

/**
 * @property ?array $data
 * @property ?bool  $flag
 * @property ?int   $deleted
 */
enum FooEnum: string
{
   case One = 'one';
   case Two = 'two';
   case Three = 'three';
}
