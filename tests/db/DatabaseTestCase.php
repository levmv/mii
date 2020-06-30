<?php declare(strict_types=1);

namespace miit\db;

use miit\TestCase;

class DatabaseTestCase extends TestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->mockWebApplication(
            [
                'components' => [
                    'db' => [
                        'username'   => 'root',
                        'password'   => 'localroot',
                        'database'   => 'miitest',
                    ],
                ],
            ]
        );
    }
}
