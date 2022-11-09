<?php declare(strict_types=1);

namespace miit\core;

use mii\core\ACL;
use mii\core\Router;
use miit\TestCase;

class RouterTest extends TestCase
{
    public function testDefault()
    {
        $all = $this->getRoutes();
        foreach ($all as $name => $group) {
            $router = new Router($group[0]);

            foreach ($group[1] as $uri => $result) {
                $params = $router->match($uri);
                $this->assertEquals($result, $params, $name.'; '.$uri);
            }
        }
    }



    protected function getRoutes()
    {
        return [
            'simple routes' => [
                [
                    'namespace' => 'app\\test',
                    'routes' => [
                        '/' => 'test',
                        'test' => 'test',
                    ],
                ],
                [
                    '/test' => ['controller' => 'app\\test\\Test', 'action' => 'index'],
                    '/test/' => ['controller' => 'app\\test\\Test', 'action' => 'index'],
                    '/' => ['controller' => 'app\\test\\Test', 'action' => 'index'],
                    'test' => ['controller' => 'app\\test\\Test', 'action' => 'index'],
                    'test/index' => null,//['controller' => 'app\\test\\Test', 'action' => 'index'],
                    'some' => null,
                ],
            ],
            'base routes' => [
                [
                    'namespace' => 'app\\test',
                    'routes' => [
                        'foo/bar' => 'test',
                        'foo/bar2' => 'foo:bar',
                        'foo(/bar(/{id}))' => 'test',
                    ],
                ],
                [
                    'foo/bar' => ['controller' => 'app\\test\\Test', 'action' => 'index'],
                    'foo/bar2' => ['controller' => 'app\\test\\Foo', 'action' => 'bar'],
                    'foo/bar/3' => ['controller' => 'app\\test\\Test', 'action' => 'index', 'id' => '3'],
                    'foo/bar/abv' => false,
                ],
            ],
            'params' => [
                [
                    'namespace' => 'app\\test',
                    'routes' => [
                        'foo/{path}(/{some})' => [
                            'path' => 'foo:bar',
                            'params' => [
                                'path' => '[a-z0-9-]+',
                                'some' => '[0-9]+',
                            ],
                        ],
                        'foo/{slug}/{id}' => 'foo',
                    ],
                ],
                [
                    'foo/as-df/1' => ['controller' => 'app\\test\\Foo', 'action' => 'bar', 'path' => 'as-df', 'some' => '1'],
                    'foo/as_df/A' => false,
                    'foo/a-0_1.3/1/' => ['controller' => 'app\\test\\Foo', 'action' => 'index', 'slug' => 'a-0_1.3', 'id' => '1'],
                ],
            ],
        ];
    }
}
