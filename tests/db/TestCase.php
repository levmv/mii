<?php
namespace yiiunit\framework\db;

use mii\db\Database;
use yiiunit\TestCase as TestCase;

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
    }

}