<?php

namespace mii\console;

\defined('STDIN') or define('STDIN', fopen('php://stdin', 'r'));
\defined('STDOUT') or define('STDOUT', fopen('php://stdout', 'w'));
\defined('STDERR') or define('STDERR', fopen('php://stderr', 'w'));

/**
 * Class App
 * @property \mii\console\Request $request
 */
class App extends \mii\core\App
{

    public function run() {

        try {
            return $this->request->execute();
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function default_components() : array {
        return [
            'log' => 'mii\log\Logger',
            'blocks' => 'mii\web\Blocks',
            'router' => 'mii\core\Router',
            'db' => 'mii\db\Database',
            'cache' => 'mii\cache\Apcu',
            'request' => 'mii\console\Request',
            'error' => 'mii\console\ErrorHandler'
        ];
    }


}