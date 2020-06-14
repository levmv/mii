<?php

namespace miit\db;


use mii\db\ModelNotFoundException;
use mii\db\Result;
use mii\db\SelectQuery;
use mii\util\Text;
use miit\data\models\Article;
use miit\data\models\Item;

class OrmTest extends DatabaseTestCase
{

    public function testCreateUpdate()
    {
        $item = new Item;
        $item->name = 'foo';

        $this->assertTrue($item->changed('name'));
        $this->assertTrue($item->changed(['name']));
        $this->assertFalse($item->loaded());
        $this->assertSame(1, $item->create());
        $this->assertTrue($item->loaded());
        $this->assertSame(1, $item->id);
        $this->assertSame(time(), $item->created);

        $this->assertEquals(2,
            (new Item([
                'name' => 'test2'
            ]))->create()
        );

        $item->name = 'bar';
        $this->assertTrue($item->changed('name'));
        $this->assertTrue($item->was_changed('name'));
        $this->assertSame(1, $item->update());
        $this->assertFalse($item->changed('name'));
        $this->assertTrue($item->was_changed('name'));

        $item = Item::one(2);
        $this->assertFalse($item->changed('name'));
        $item->name = 'bar';
        $item->update();
        $this->assertFalse($item->changed('name'));
        $this->assertTrue($item->was_changed('name'));

        $item = Item::one(1);
        $item->name = 'bar';
        $this->assertFalse($item->changed('name'));
        $this->assertSame(0, $item->update());
        $this->assertFalse($item->was_changed('name'));
    }

    public function testFind()
    {
        $item = Item::find()->where('id', '=', 1)->one();

        $this->assertInstanceOf(Item::class, $item);
        $this->assertTrue($item->loaded());
        $this->assertSame('bar', $item->name);
        $this->assertSame(1, $item->id);
    }


    public function testWHere()
    {
        $item = Item::where(['id', '=', 1])->one();

        $this->assertInstanceOf(Item::class, $item);
        $this->assertSame(1, $item->id);

        $item = Item::where([
            ['id', '=', 1],
            ['created', '<=', time()],
        ])->one();
        $this->assertSame(1, $item->id);
    }

    public function testAll()
    {
        $items = Item::all([1,2]);

        $this->assertIsArray($items);
        $this->assertCount(2, $items);
        $this->assertInstanceOf(Item::class, $items[0]);
    }

    public function testJson()
    {
        $arr = [1, "foo" => "bar"];
        $arr2 = ["new" => "list"];

        $a = new Article();
        $a->data = $arr;
        $a->create();

        $this->assertSame($arr, $a->data);
        $this->assertTrue($a->was_changed('data'));

        $a = Article::one(1);
        $a->data = $arr;
        $this->assertFalse($a->changed(['data']));
        $this->assertSame($a->data, $arr);

        $a->data = $arr2;
        $this->assertTrue($a->changed(['data']));
        $this->assertSame(1, $a->update());
    }

    public function testDelete()
    {
        $item = Item::one(2);

        $item->delete();

        $this->assertFalse($item->loaded());

        $item = Item::one(2);

        $this->assertNull($item);
    }

    public function testToArray()
    {
        $item = new Item([
            'id' => 3,
            'name' => 'foo'
        ]);
        $this->assertSame([
            'id' => 3,
            'name' => 'foo'
        ], $item->to_array());

        $item = new Article([
            'id' => 3,
            'data' => [1]
        ]);

        $this->assertSame([
            'id' => 3,
            'data' => [1]
        ], $item->to_array());

    }

}
