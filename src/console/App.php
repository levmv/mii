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

    public function run() {
        try {
            $this->request->execute()->send();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function default_components() : array {
        return array_merge(parent::default_components(), [
            'request' => 'mii\console\Request',
            'response' => 'mii\console\Response',
            'error' => 'mii\console\ErrorHandler'
        ]);
    }


}