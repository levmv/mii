<?php

namespace mii\console;

defined('STDIN') or define('STDIN', fopen('php://stdin', 'r'));
defined('STDOUT') or define('STDOUT', fopen('php://stdout', 'w'));
defined('STDERR') or define('STDERR', fopen('php://stderr', 'w'));

/**
 * Class App
 * @property \mii\console\Request $request
 * @property \mii\console\Response $response
 */
class App extends \mii\core\App
{

    protected $_blocks;

    protected $_session;


    public function run() {
        try {
            $this->request = new Request();
            $this->request->execute()->send();

        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function default_components() : array {
        return array_merge(parent::default_components(), [
            'request' => ['class' => 'mii\console\Request'],
            'response' => ['class' => 'mii\console\Response'],
            'error' => ['class' => 'mii\console\ErrorHandler']
        ]);
    }


}