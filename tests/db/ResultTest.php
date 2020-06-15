<?php

namespace miit\db;

use mii\db\DB;
use mii\db\Expression;
use mii\db\Query;
use mii\db\SelectQuery;
use miit\data\models\Item;

class ResultTest extends DatabaseTestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        DB::alter('TRUNCATE TABLE items');

        for($i=0;$i<100;$i++) {
            $item = new Item;
            $item->name = "str $i";
            $item->create();
        }
    }


    public function testAll()
    {
        $result = Item::find()->get();

        $all = $result->all();
        $this->assertIsArray($all);
        $this->assertCount(100, $all);
        $this->assertInstanceOf(Item::class, $all[0]);
    }

    public function testArrayAccess()
    {
        $result = Item::find()->limit(3)->get();

        foreach($result as $item) {
            $this->assertInstanceOf(Item::class, $item);
        }

        $this->assertCount(3, $result);
        $this->assertInstanceOf(Item::class, $result[1]);
    }

    public function testArrayAccess2()
    {
        $result = Item::find()->as_array()->limit(3)->get();

        foreach($result as $item) {
            $this->assertIsArray($item);
            $this->assertIsInt($item['id']);
        }
    }

    public function testEach()
    {
        $result = Item::find()->limit(3)->get();

        $result->each(function($item) {
            $this->assertInstanceOf(Item::class, $item);
        });
    }

    public function testIndex()
    {
        $result = Item::find()
            ->where('id', 'in', [10,15,20])
            ->get()
            ->index_by('id')
            ->all();

        $this->assertIsArray($result);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(15, $result);
        $this->assertArrayHasKey(20, $result);

        $this->assertInstanceOf(Item::class, $result[15]);

        $result = Item::find()
            ->where('id', 'in', [10,20])
            ->as_array()
            ->get()
            ->index_by('id')
            ->all();

        $this->assertIsArray($result);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
    }

    public function testColumn()
    {
        $result = Item::find()->where('id', '=', 50)->get();

        $this->assertSame(50, $result->column('id'));

        $result = Item::find()->where('id', 'in', [11,12,13])->get();

        $this->assertSame([11,12,13], $result->column_values('id'));
    }

    public function testScalar()
    {
        $result = Item::find()->select([new Expression('COUNT(*)')])->limit(1)->get();

        $this->assertSame(100, $result->scalar());
    }

}
