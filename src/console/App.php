<?php /** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

namespace mii\console;

\defined('STDIN') or \define('STDIN', \fopen('php://stdin', 'rb'));
\defined('STDOUT') or \define('STDOUT', \fopen('php://stdout', 'w'));
\defined('STDERR') or \define('STDERR', \fopen('php://stderr', 'w'));

/**
 * Class App
 * @property Request $request
 */
class App extends \mii\core\App
{
    /**
     * @throws \mii\core\Exception
     * @throws \Throwable
     */
    public function run(): void
    {
        exit($this->request->execute());
    }

    protected function defaultComponents(): array
    {
        return [
            'log' => \mii\log\Logger::class,
            'blocks' => \mii\web\Blocks::class,
            'router' => \mii\core\Router::class,
            'db' => \mii\db\Database::class,
            'cache' => \mii\cache\Apcu::class,
            'request' => Request::class,
            'error' => ErrorHandler::class,
        ];
    }
}
