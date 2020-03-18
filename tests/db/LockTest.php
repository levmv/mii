<?php

namespace mii\tests\db;

use mii\core\ACL;
use mii\db\Query;
use mii\tests\TestCase;


class LockTest extends TestCase
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
                            'database'   => 'miitest',
                        ],
                        'charset'      => 'utf8'
                    ],
                ]
            ]
        );
    }


    public function testLock() {
        $this->assertEquals(\Mii::$app->db->get_lock('test', 10), true);

//        $this->assertFalse(\Mii::$app->db->get_lock('test', 10));
    }



}