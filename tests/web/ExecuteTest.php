<?php declare(strict_types=1);

namespace miit\web;

use mii\web\BadRequestHttpException;
use mii\web\Response;
use miit\data\controllers\OneController;
use miit\TestCase;

class ExecuteTest extends TestCase
{
    protected function setUp() : void
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    /**
     * @dataProvider provideOkData
     */
    public function testWorking(string $action, array $params)
    {
        $v = new OneController();
        $response = new Response();
        $v->response = $response;
        $v->execute($action, $params);
        $this->assertEquals('ok', $response->content());
    }


    public function provideOkData(): array
    {
        return [
            ['noparams', []],
            ['noparams', ['some' => 'param']], // Yep, it's ok
            ['justint', ['id' => '123']],
            ['justint', ['id' => '0123']],
            ['manyparams', ['id' => '123', 'path' => 'foo/bar']],
            ['manyparams', ['id' => '123', 'path' => 'foo/bar', 'foo' => 'bar']],
            ['notypes', ['id' => '123', 'request' => 'foobar']],
            ['notypes2', ['id' => '123', 'request' => 'foobar']],
            ['somedi', []],
            ['somedi2', ['id' => '123']],
            ['somedi3', ['id' => '123']],
        ];
    }

    /**
     * @dataProvider provideBadData
     */
    public function testNotWorking(string $action, array $params)
    {
        $this->expectException(BadRequestHttpException::class);

        $v = new OneController();
        $response = new Response();
        $v->response = $response;
        $v->execute($action, $params);
        $this->assertNotEquals('ok', $response->content());
    }


    public function provideBadData(): array
    {
        return [
            ['justint', ['anotherid' => '123']],
            ['justint', ['id' => 'notnumeric']],
            ['manyparams', ['id' => '123', 'foo' => 'bar']],
            ['manyparams', ['id' => '123', 'notpath' => 'foo/bar']],
            ['manyparams', ['id' => '123', 'path' => null, 'foo' => 'bar']],
            ['somedi2', ['request' => '123']],
            ['somedi3', ['notid' => '123']],
            //['somedi2', ['request' => '123', ]],
        ];
    }

    /**
     * @dataProvider provideVeryBadData
     */
    public function testTypeError(string $action, array $params)
    {
        $this->expectException(\TypeError::class);

        $v = new OneController();
        $response = new Response();
        $v->response = $response;
        $v->execute($action, $params);
    }


    public function provideVeryBadData(): array
    {
        return [
            ['somedi3', ['request' => '123', 'id' => '123']],
        ];
    }
}
