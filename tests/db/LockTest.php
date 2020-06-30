<?php declare(strict_types=1);

namespace miit\db;

use miit\TestCase;

class LockTest extends DatabaseTestCase
{
    public function testLock()
    {
        $this->assertEquals(\Mii::$app->db->getLock('test', 10), true);

//        $this->assertFalse(\Mii::$app->db->get_lock('test', 10));
    }
}
