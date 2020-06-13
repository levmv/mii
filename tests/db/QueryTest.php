<?php

namespace miit\db;

use mii\db\Expression;
use mii\db\Query;
use mii\db\SelectQuery;

class QueryTest extends DatabaseTestCase
{

    public function testInsert()
    {
        $this->assertEquals(
            "INSERT INTO `t` (`a`, `b`, `c`) VALUES (1, '2', 3)",

            (new Query())
                ->insert('t')
                ->columns(['a', 'b', 'c'])
                ->values([1, '2', 3])
                ->compile()
        );
    }

    public function testInsertData()
    {
        $this->assertEquals(
            "INSERT INTO `t` (`a`, `b`) VALUES (1, 2)",

            (new Query())
                ->insert('t', [
                    'a' => 1,
                    'b' => 2
                ])
                ->compile()
        );
    }

    public function testUpdate()
    {
        $this->assertEquals(
            "UPDATE `t` SET `a` = 1, `b` = 2, `c` = 3 WHERE `id` = 123",

            (new Query())
                ->update('t')
                ->set([
                    'a' => 1,
                    'b' => 2,
                    'c' => 3
                ])
                ->where('id', '=', 123)
                ->compile()
        );
    }

    public function testDelete()
    {
        $this->assertEquals(
            "DELETE FROM `t` WHERE `id` = 123",

            (new Query())
                ->delete('t')
                ->where('id', '=', 123)
                ->compile()
        );
    }


}
