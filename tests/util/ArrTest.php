<?php declare(strict_types=1);

namespace miit\util;

use mii\util\Arr;
use miit\TestCase;

class ArrTest extends TestCase
{
    public function testOnly()
    {
        $input = [
            'one' => 'value',
            'two' => null,
            'tree' => [1, 2, 3]
        ];

        $this->assertEquals($input, Arr::only($input, [
            'one',
            'two',
            'tree'
        ]));

        $this->assertEquals(['one' => 'value'], Arr::only($input, [
            'one',
        ]));

        $this->assertEquals(['newOne' => 'value'], Arr::only($input, [
            'newOne' => 'one',
        ]));

        $this->assertEquals(['tree' => 6], Arr::only($input, [
            'tree' => array_sum(...),
        ]));

        $this->assertEquals([], Arr::only($input, []));
    }

}
