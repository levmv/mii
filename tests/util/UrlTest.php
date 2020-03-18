<?php

namespace mii\tests\db;

use mii\core\ACL;
use mii\db\Query;
use mii\tests\TestCase;
use mii\util\URL;
use mii\web\Request;


class UrlTest extends TestCase
{


    protected function setUp()
    {
        parent::setUp();

        $_SERVER['HTTP_HOST'] = 'test.com';
        $_SERVER['SERVER_NAME'] = '';
        $_SERVER['REQUEST_URI'] = "/";

        $this->mockWebApplication(
            [

            ]
        );
    }


    public function testSite() {

        $this->assertEquals('/foo/bar', URL::site('foo/bar'));
        $this->assertEquals('/foo/bar', URL::site('/foo/bar'));
        $this->assertEquals('http://test.com/foo/bar', URL::site('foo/bar', true));
        $this->assertEquals('//test.com/foo/bar', URL::site('foo/bar', '//'));
        $this->assertEquals('https://test.com/foo/bar', URL::site('foo/bar', 'https'));
    }

    public function testBase() {

        $this->assertEquals('/', URL::base());
        $this->assertEquals('http://test.com/', URL::base(true));
        $this->assertEquals('https://test.com/', URL::base('https'));
        $this->assertEquals('//test.com/', URL::base('//'));

        \Mii::$app->base_url = '/base';

        $this->assertEquals('/base', URL::base());
        $this->assertEquals('http://test.com/base', URL::base(true));
        $this->assertEquals('https://test.com/base', URL::base('https'));
        $this->assertEquals('//test.com/base', URL::base('//'));
    }

    public function testCurrent() {
        \Mii::$app->request->uri("");
        $this->assertEquals('/', URL::current());

        \Mii::$app->request->uri("/");
        $this->assertEquals('/', URL::current());

        \Mii::$app->request->uri("/test");
        $this->assertEquals('/test', URL::current());
    }

}