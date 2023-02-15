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


    public function testPath()
    {
        $input = [
            'one' => 'value',
            'two' => [
                'foo' => 1,
                'bar' => 3,
            ],
            'three' => [
                'deep' => [
                    'foo' => "a"
                ],
                "foo" => [],
                "bar" => null,
            ]
        ];

        $this->assertEquals('value', Arr::path($input, 'one'));
        $this->assertEquals(['foo' => 1, 'bar' => 3], Arr::path($input, 'two'));
        $this->assertEquals(3, Arr::path($input, 'two.bar'));
        $this->assertEquals('a', Arr::path($input, 'three.deep.foo'));
        $this->assertEquals([], Arr::path($input, 'three.foo'));
        $this->assertEquals(null, Arr::path($input, 'three.bar'));
        $this->assertEquals(1, Arr::path($input, 'three.deep.existed', 1));
    }

    public function testSetPath()
    {
        $arr = [
            'foo' => [
                'bar' => false
            ]
        ];

        Arr::setPath($arr, 'foo.bar', true);
        $this->assertEquals(true, $arr['foo']['bar']);

        Arr::setPath($arr, 'foo.some', null);
        $this->assertEquals(null, $arr['foo']['some']);


        Arr::setPath($arr, 'new.some.key', [1]);
        $this->assertEquals([1], $arr['new']['some']['key']);

        Arr::setPath($arr, 'foo', false);
        $this->assertEquals(false, $arr['foo']);
    }

}
