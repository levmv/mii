<?php

namespace miit\db;

use mii\db\Query;
use mii\db\SelectQuery;
use miit\TestCase;

class BuilderTest extends DatabaseTestCase
{

    public function testSimpleSelect() {
        $this->assertEquals(
            "SELECT `table`.`name` FROM `table` WHERE `field` = 1",
            (new SelectQuery())
            ->select(['name'])
            ->from('table')
            ->where('field', '=', 1)
            ->compile()
           );
    }


  /*  public function testEscape() {
        $this->assertEquals(
            "SELECT `name\"'` FROM `table\"\\"``` WHERE `field``'` = '\\",`'",

            (new Query())
                ->select(['name"\''])
                ->from('table"\"`')
                ->where('field`\'', '=', '",`')
                ->compile(),

            'SELECT `name"\'` FROM `table"\"``` WHERE `field``\'` = \'\",`\''
        );
    }*/


    /**
     * @dataProvider conditionProvider
     * @param array $condition
     * @param string $expected
     * @param array $expectedParams
     * @throws \Exception
     */
    public function testBuildCondition($condition, $expected, $expectedParams)
    {
        $query = (new SelectQuery())->from('table')->where(...$condition);

        $this->assertEquals(
            'SELECT `table`.* FROM `table` ' . (empty($expected) ? '' : 'WHERE ' . $expected),
            $query->compile()
        );
    }


    public function conditionProvider()
    {
        $conditions = [
            [['col', '=', 1], '`col` = 1', []],

            [[[
                    ['col1', '=', 1],
                    ['col2', '=', 2]], null, null
                ]
                , '`col1` = 1 AND `col2` = 2', []]


        ];

        return $conditions;
    }


    public function testExtSelect() {

        $this->assertEquals(

            (new Query())
                ->select(['col1', 'col2', 'col3'])
                ->from(['table', 't'])
                ->where()
                    ->where('col1', '=', 1)
                    ->where()
                        ->where('col2', '=', 2)
                        ->or_where('col3', '=', 4)
                    ->end()
                ->end()
                ->or_where()
                    ->where('col2', '=', 5)
                    ->or_where('col3', '=', "six")
                ->end()
                ->where()
                    ->where('col3', '=', 7)
                ->end()
                ->having('col1', '<', 5)
                ->having()
                    ->having('col2', '=', 'col3')
                    ->or_having('col3', '=', 5)
                ->end()
                ->order_by('col3', 'desc')
                ->compile(),

            "SELECT `col1`, `col2`, `col3` FROM `table` AS `t` WHERE (`col1` = 1 AND (`col2` = 2 OR `col3` = 4)) OR (`col2` = 5 OR `col3` = 'six') AND (`col3` = 7) HAVING `col1` < 5 AND (`col2` = 'col3' OR `col3` = 5) ORDER BY `col3` DESC");
    }



}
