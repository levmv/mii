<?php declare(strict_types=1);

namespace miit\db;

use mii\db\DatabaseException;
use mii\db\ModelNotFoundException;
use mii\db\ORM;
use mii\db\Result;
use mii\db\SelectQuery;
use mii\util\Text;
use miit\data\models\Article;
use miit\data\models\Article2;
use miit\data\models\Article3;
use miit\data\models\Article4;
use miit\data\models\ArticleQQ;
use miit\data\models\FooEnum;
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
        $this->assertSame(\time(), $item->created);

        $this->assertEquals(
            2,
            (new Item([
                'name' => 'test2',
            ]))->create()
        );

        $item->name = 'bar';
        $this->assertTrue($item->changed('name'));
        $this->assertTrue($item->wasChanged('name'));
        $this->assertSame(1, $item->update());
        $this->assertFalse($item->changed('name'));
        $this->assertTrue($item->wasChanged('name'));

        $item = Item::one(2);
        $this->assertFalse($item->changed('name'));
        $item->name = 'bar';
        $item->update();
        $this->assertFalse($item->changed('name'));
        $this->assertTrue($item->wasChanged('name'));

        $item = Item::one(1);
        $this->assertFalse($item->changed('some'));
        $item->some = null;
        $this->assertFalse($item->changed('some'));

        $item = Item::one(1);
        $item->name = null;
        $this->assertTrue($item->changed('name'));
        $this->expectException(DatabaseException::class);
        $item->update();
        $this->assertFalse($item->wasChanged('name'));
    }

    public function testFind()
    {
        $item = Item::find()->where('id', '=', 1)->one();

        $this->assertInstanceOf(Item::class, $item);
        $this->assertTrue($item->loaded());
        $this->assertSame('bar', $item->name);
        $this->assertSame(1, $item->id);
    }

    public function testFindCount()
    {
        $count = Item::find()->where('id', '=', 1)->count();
        $this->assertSame(1, $count);
    }


    public function testWhere()
    {
        $item = Item::where(['id', '=', 1])->one();

        $this->assertInstanceOf(Item::class, $item);
        $this->assertSame(1, $item->id);

        $item = Item::where([
            ['id', '=', 1],
            ['created', '<=', \time()],
        ])->one();
        $this->assertSame(1, $item->id);

        $this->assertSame(
            'SELECT `items`.* FROM `items` WHERE `id` = 1',
            Item::where('id', '=', 1)->compile()
        );

        $this->assertSame(
            'SELECT `items`.* FROM `items` WHERE `id` = 1',
            Item::where(['id', '=', 1])->compile()
        );

        $this->assertSame(
            "SELECT `items`.* FROM `items` WHERE `id` = 1 AND `data` = ''",
            Item::where([
                ['id', '=', 1],
                ['data', '=', ''],
            ])->compile()
        );
    }

    public function testAll()
    {
        $items = Item::all();
        $this->assertIsArray($items);
        $this->assertCount(2, $items);
        $this->assertInstanceOf(Item::class, $items[0]);

        $items = Item::all(['id', '=', 1]);
        $this->assertIsArray($items);
        $this->assertCount(1, $items);
        $this->assertInstanceOf(Item::class, $items[0]);

        $items = Item::all('id', 'in', [1, 2]);

        $this->assertIsArray($items);
        $this->assertCount(2, $items);
        $this->assertInstanceOf(Item::class, $items[0]);
    }

    public function testJson()
    {
        $arr = [1, 'foo' => 'bar'];
        $arr2 = ['new' => 'list'];

        $a = new Article();
        $a->data = $arr;
        $a->create();

        $this->assertSame($arr, $a->data);
        $this->assertTrue($a->wasChanged('data'));

        $a = Article::one(1);
        $a->data = $arr;
        $this->assertFalse($a->changed(['data']));
        $this->assertSame($a->data, $arr);

        $a->data = $arr2;
        $this->assertTrue($a->changed(['data']));
        $this->assertSame(1, $a->update());
    }


    public function testCast()
    {
        $arr = [1, 'foo' => 'bar'];
        $arr2 = ['new' => 'list'];

        $a = new Article2();
        $a->data = $arr;
        $a->create();

        $id = $a->id;

        $this->assertSame($arr, $a->data);
        $this->assertTrue($a->wasChanged('data'));

        $a = Article2::one($id);
        $a->data = $arr;
        $this->assertFalse($a->changed(['data']));
        $this->assertSame($a->data, $arr);

        $a->data = $arr2;
        $this->assertTrue($a->changed(['data']));
        $this->assertSame(1, $a->update());

        $a->flag = true;
        $this->assertTrue($a->changed(['flag']));
        $this->assertSame($a->flag, true);
        $a->update();

        $a = Article2::one($id);
        $this->assertSame($a->flag, true);
        $a->flag = "ok";
        $this->assertSame($a->flag, true);
        $a->update();

        $a = Article2::one($id);
        $this->assertSame($a->flag, true);

        $a->deleted = "123";
        $a->update();
        $this->assertTrue($a->wasChanged('deleted'));
        $a->deleted = 123;

        $this->assertFalse($a->changed('deleted'));
    }

    public function testCastNullable()
    {
        /*  $a = new Article2();
          $a->data = null;
          $a->flag = null;
          $a->deleted = null;

          $this->assertSame([], $a->data);
          $this->assertSame(false, $a->flag);
          $this->assertSame(0, $a->deleted);
        */

        $a = new Article2();
        $a->data = null;
        $a->flag = null;
        $a->deleted = null;

        $this->assertSame(null, $a->data);
        $this->assertSame(null, $a->flag);
        $this->assertSame(null, $a->deleted);
    }

    public function testEnumCast()
    {
        $a = new Article4();

        $a->name = FooEnum::Two;
        $a->data = '';
        $this->assertSame(true, $a->changed('name'));
        $this->assertSame(FooEnum::Two, $a->name);


        $id = $a->create();

        $a = Article4::one($id);
        $this->assertSame(FooEnum::Two, $a->name);
    }

    public function testJsonSerializable()
    {
        $json = ['id' => 1, 'name' => '', 'data' => ['new' => 'list'], 'flag' => 0, 'deleted' => 0];

        $a = Article::one(1);

        $this->assertSame(\json_encode($json), \json_encode($a));
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
            'name' => 'foo',
        ]);
        $this->assertSame([
            'id' => 3,
            'name' => 'foo',
        ], $item->toArray());

        $item = new Article([
            'id' => 3,
            'data' => [1],
        ]);

        $this->assertSame([
            'id' => 3,
            'data' => [1],
        ], $item->toArray());
    }
}
