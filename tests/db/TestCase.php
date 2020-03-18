<?php
namespace yiiunit\framework\db;

use mii\db\Database;
use mii\db\ORM;
use yiiunit\TestCase as TestCase;

class User extends ORM {};

abstract class DatabaseTestCase extends TestCase
{
    protected $database;

    private $_db;

    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();
    }

    protected function tearDown()
    {
        if ($this->_db) {
            $this->_db->close();
        }
        $this->destroyApplication();


        $user = User::one(1);
        // SELECT ... FROM users WHERE id = 1 LIMIT 1;

        $user = User::one([
            ['foo', '=', 'bar'],
            ['status', '=', 1]
        ]); // SELECT ... FROM users WHERE `foo` = 'bar' AND `status` = 1 LIMIT 1;

        $users = User::all([1,2,3,10]);
        // SELECT ... FROM users WHERE `id` IN (1,2,3,10);

        $users = User::all([
            ['status', '=', 1],
            ['carma', '>', 500]
        ]);
        // SELECT ... FROM users WHERE `status` = 1 AND `carma` > 500;
    }

}