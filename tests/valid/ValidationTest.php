<?php declare(strict_types=1);

namespace miit\valid;

use mii\util\Url;
use mii\valid\Validation;
use mii\valid\Validator;
use miit\TestCase;

class ValidationTest extends TestCase
{


    /**
     * @dataProvider provideBasicData
     */
    public function testBasics(bool $expected, array $input)
    {
        $v = new Validator($input, [
            'name' => 'required|max:6',
            'email' => 'email'
        ]);
        $this->assertEquals($expected, $v->validate());
    }


    public function provideBasicData(): array
    {
        return [
            [true, ['name' => 'abcdef']],
            [true, ['name' => 'abcdef', 'email' => 'foo@bar.com']],
            //FIXME [true, ['name' => 'abcdef', 'email' => '']],
            [false, ['name' => 'abcdef', 'email' => 'foo@bar@com']],
            [false, ['name' => 'abcdefg']],
            [false, ['name' => '']],
            [false, ['name' => null]],
            [false, []]
        ];
    }

    /**
     * @dataProvider provideMinMaxData
     */
    public function testMinMax(bool $expected, array $input)
    {
        $v = new Validator($input, [
            'int' => 'numeric|min:5|max:10',
            'float' => 'numeric|min:0.5|max:1',
            'str' => 'min:3|max:6',
            'str2' => 'between:2,10',
            'int2' => 'numeric|between:1,10'
        ]);

        $this->assertEquals($expected, $v->validate());
    }


    public function provideMinMaxData(): array
    {
        return [
            [true, ['int' => 6, 'float' => 0.6, 'str' => 'abcd']],
            [true, ['int' => '6', 'float' => '0.6', 'int2' => 5]],
            [true, ['str2' => 'bar']],
            [false, ['int' => 3, 'float' => 0.6, 'str' => 'ab']],
            [false, ['float' => 0.3]],
            [false, ['str' => 'ab']],
            [false, ['int' => '11']],
            [false, ['int2' => '11']],
            [false, ['int' => 'a5']],
            [false, ['str2' => 'a']],
            [false, ['float' => '0.1']],
        ];
    }

    /**
     * @dataProvider provideClosureData
     */
    public function testClosure(bool $expected, array $input)
    {
        $v = new Validator($input, [
            'name' => function (Validator $v, string $field, mixed $value) {
                if ($value !== 'abc') {
                    $v->error($field, $field);
                    return false;
                }
                return true;
            },
            'surname' => ['max:123', function (Validator $v, string $field, mixed $value) {
                if ($value !== 'bar') {
                    $v->error($field, $field);
                    return false;
                }
                return true;
            }]
        ]);

        $this->assertEquals($expected, $v->validate());
    }


    public function provideClosureData(): array
    {
        return [
            [true, ['name' => 'abc']],
            [false, ['name' => 'foo']],
            [true, ['name' => 'abc', 'surname' => 'bar']]
        ];
    }


    /**
     * @dataProvider provideFilterData
     */
    public function testDefaultFilter(bool $expected, array $input)
    {
        $v = new Validator($input, [
            'name' => 'required|max:6',
            'foo' => 'numeric'
        ]);
        $this->assertEquals($expected, $v->validate());
    }

    /**
     * @dataProvider provideFilterData
     */
    public function testCustomFilter(bool $expected, array $input)
    {
        $v = new Validator($input, [
            'name' => 'required|max:3',
            'foo' => 'numeric|max:124'
        ]);
        $v = $v->filter(fn($field, $value) => $field === 'name' ? 'foo' : $value);
        $this->assertEquals($expected, $v->validate());
    }


    public function provideFilterData(): array
    {
        return [
            [true, ['name' => '  foobar   ']],
            [true, ['name' => 'abcdef', 'foo' => 123]],
        ];
    }

    /**
     * @dataProvider provideValidatedData
     */
    public function testValidated(array $expected, array $input)
    {
        $v = new Validator($input, [
            'name' => 'required|max:3',
            'foo' => 'numeric|max:124'
        ]);

        $v->validate();

        $this->assertEquals($expected, $v->validated([
            'name',
            'foo'
        ]));
    }

    public function provideValidatedData(): array
    {
        return [
            [['name' => 'foobar', 'foo' => null], ['name' => '  foobar   ']],
            [['name' => 'abcdef', 'foo' => 123], ['name' => 'abcdef', 'foo' => 123]],
        ];
    }


    /**
     * @dataProvider provideArrayData
     */
    public function testArray(bool $expected, array $input)
    {
        $v = new Validator($input, [
            'data' => 'required|array',
            'data.name' => 'required|max:6',
            'data.age' => 'numeric|between:6,100',
            //'data.foo' => 'numeric|nullable',
        ]);
        $this->assertEquals($expected, $v->validate());
    }


    public function provideArrayData(): array
    {
        return [
            [true, ['data' => ['name' => 'foo', 'age' => 91, 'foo' => null]]],
        ];
    }


}
