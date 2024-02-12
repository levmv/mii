<?php declare(strict_types=1);

namespace miit;

/**
 * This is the base class for all yii framework unit tests.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    public static $params;

    /**
     * Returns a test configuration param from /data/config.php
     * @param  string $name params name
     * @param  mixed $default default value to use when param is not set.
     * @return mixed  the value of the configuration param
     */
    public static function getParam($name, $default = null)
    {
        if (static::$params === null) {
            static::$params = require __DIR__ . '/data/config.php';
        }
        return static::$params[$name] ?? $default;
    }

    /**
     * Asserting two strings equality ignoring line endings
     *
     * @param string $expected
     * @param string $actual
     */
    public function assertEqualsWithoutLE($expected, $actual)
    {
        $expected = \str_replace("\r\n", "\n", $expected);
        $actual = \str_replace("\r\n", "\n", $actual);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Clean up after test.
     * By default the application created with [[mockApplication]] will be destroyed.
     */
    protected function tearDown() : void
    {
        parent::tearDown();
        $this->destroyApplication();
    }

    /**
     * Destroys application in Mii::$app by setting it to null.
     */
    protected function destroyApplication()
    {
        if (\Mii::$app && \Mii::$app->hasInstance('session')) {
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
    protected function mockApplication($config = [], $appClass = '\mii\console\App')
    {
        new $appClass($config);
    }

    protected function getVendorPath()
    {
        $vendor = \dirname(__DIR__, 2) . '/vendor';
        if (!\is_dir($vendor)) {
            $vendor = \dirname(__DIR__, 4);
        }
        return $vendor;
    }

    protected function mockWebApplication($config = [], $appClass = '\mii\web\App')
    {
        new $appClass($config);
    }


    /**
     * Invokes a inaccessible method.
     * @param $object
     * @param $method
     * @param array $args
     * @param bool $revoke whether to make method inaccessible after execution
     * @return mixed
     * @since 2.0.11
     */
    protected function invokeMethod($object, $method, $args = [], $revoke = true)
    {
        $reflection = new \ReflectionObject($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        $result = $method->invokeArgs($object, $args);
        if ($revoke) {
            $method->setAccessible(false);
        }

        return $result;
    }
}
