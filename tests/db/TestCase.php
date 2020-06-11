<?php

namespace mii\tests\db;

use mii\core\ACL;
use mii\db\Query;


class TestCase extends \mii\tests\TestCase
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

}
