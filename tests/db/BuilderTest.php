<?php

namespace mii\tests\db;

use mii\core\ACL;
use mii\db\Query;
use mii\tests\TestCase;


class BuilderTest extends TestCase
{


    protected function setUp()
    {
        parent::setUp();

        $this->mockWebApplication(
            [
                'components' => [
                    'db' => [
                        'connection' => [

                            'hostname'   => 'localhost',
                            'username'   => 'root',
                            'password'   => 'localroot',
                            'database'   => 'storetest',
                        ],
                        'charset'      => 'utf8'
                    ],
                ]
            ]
        );
    }


    public function testSimpleSelect() {
        $this->assertEquals(

            (new Query())
            ->select(['name'])
            ->from('table')
            ->where('field', '=', 1)
            ->compile(),

            "SELECT `name` FROM `table` WHERE `field` = 1");
    }


    public function testExtSelect() {
        \Mii::$app->db->escape('%sdfsdf%%sfg%_');
        echo \Mii::$app->db;
        exit;

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