<?php

namespace miit\db;

use mii\core\ACL;
use mii\db\Query;
use mii\util\Url;
use mii\web\Request;
use miit\TestCase;


class UrlTest extends TestCase
{


    protected function setUp() : void
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

        $this->assertEquals('/foo/bar', Url::site('foo/bar'));
        $this->assertEquals('/foo/bar', Url::site('/foo/bar'));
        $this->assertEquals('http://test.com/foo/bar', Url::site('foo/bar', true));
        $this->assertEquals('//test.com/foo/bar', Url::site('foo/bar', '//'));
        $this->assertEquals('https://test.com/foo/bar', Url::site('foo/bar', 'https'));
    }

    public function testBase() {

        $this->assertEquals('', Url::base());
        $this->assertEquals('http://test.com', Url::base(true));
        $this->assertEquals('https://test.com', Url::base('https'));
        $this->assertEquals('//test.com', Url::base('//'));

        \Mii::$app->base_url = '/base';

        $this->assertEquals('/base', Url::base());
        $this->assertEquals('http://test.com/base', Url::base(true));
        $this->assertEquals('https://test.com/base', Url::base('https'));
        $this->assertEquals('//test.com/base', Url::base('//'));
    }

    public function testCurrent() {
        \Mii::$app->request->uri("");
        $this->assertEquals('/', Url::current());

        \Mii::$app->request->uri("/");
        $this->assertEquals('/', Url::current());

        \Mii::$app->request->uri("/test");
        $this->assertEquals('/test', Url::current());
    }

}
