<?php

namespace mii\tests;


/**
 * This is the base class for all yii framework unit tests.
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    public static $params;

    /**
     * Returns a test configuration param from /data/config.php
     * @param  string $name params name
     * @param  mixed $default default value to use when param is not set.
     * @return mixed  the value of the configuration param
     */
    public static function getParam($name, $default = null) {
        if (static::$params === null) {
            static::$params = require(__DIR__ . '/data/config.php');
        }
        return isset(static::$params[$name]) ? static::$params[$name] : $default;
    }

    /**
     * Asserting two strings equality ignoring line endings
     *
     * @param string $expected
     * @param string $actual
     */
    public function assertEqualsWithoutLE($expected, $actual) {
        $expected = str_replace("\r\n", "\n", $expected);
        $actual = str_replace("\r\n", "\n", $actual);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Clean up after test.
     * By default the application created with [[mockApplication]] will be destroyed.
     */
    protected function tearDown() {
        parent::tearDown();
        $this->destroyApplication();
    }

    /**
     * Destroys application in Yii::$app by setting it to null.
     */
    protected function destroyApplication() {
        if (\Mii::$app && \Mii::$app->has('session', true)) {
            \Mii::$app->session->close();
        }
        \Mii::$app = null;
    }

    /**
     * Populates Mii::$app with a new application
     * The application will be destroyed on tearDown() automatically.
     * @param array $config The application configuration, if needed
     * @param string $appClass name of the application class to create
     */
    protected function mockApplication($config = [], $appClass = '\mii\console\App') {
        new $appClass($config);
    }

    protected function getVendorPath() {
        $vendor = dirname(dirname(__DIR__)) . '/vendor';
        if (!is_dir($vendor)) {
            $vendor = dirname(dirname(dirname(dirname(__DIR__))));
        }
        return $vendor;
    }

    protected function mockWebApplication($config = [], $appClass = '\mii\web\App') {
        new $appClass($config);
    }
}