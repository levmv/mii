<?php declare(strict_types=1);

namespace mii\console;

use mii\core\Component;
use mii\core\Exception;
use mii\util\Console;

class Request extends Component
{
    /**
     * @var  array   parameters from the route
     */
    public array $params = [];

    /**
     * @var  string  controller to be executed
     */
    public $controller;

    /**
     * @var  string  action to be executed in the controller
     */
    public string $action;


    public function init(array $config = []): void
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }

        if (isset($_SERVER['argv'])) {
            $argv = $_SERVER['argv'];
            \array_shift($argv);
        } else {
            $argv = [];
        }

        if (empty($argv)) {
            return;
        }

        $this->controller = \ucfirst($argv[0]);
        \array_shift($argv);

        $this->action = 'index';

        $params = [];
        $c = 0;
        foreach ($argv as $param) {
            if (\preg_match('/^--(\w+)(=(.*))?$/', $param, $matches)) {
                $name = $matches[1];
                $value = $matches[3] ?? true;

                if (isset($params[$name])) {
                    $params[$name] = (array) $params[$name];
                    $params[$name][] = $value;
                } else {
                    $params[$name] = $value;
                }
            } elseif ($c === 0) {
                $this->action = $param;
            } else {
                $params[] = $param;
            }
            $c++;
        }

        $this->params = $params;
    }

    public function execute()
    {
        if (empty($this->controller)) {
            $this->genHelp();
            return 0;
        }

        $namespaces = config('console.namespaces', [
            'app\\console',
        ]);

        if (!\count($namespaces)) {
            throw new Exception('console.namespaces is empty');
        }

        // Failback namespace
        $namespaces[] = 'mii\\console\\controllers';

        while (\count($namespaces)) {
            $controller_class = \array_shift($namespaces) . '\\' . $this->controller;

            \class_exists($controller_class); // always return false, but autoload class if it exists

            // real check
            if (\class_exists($controller_class, false)) {
                break;
            }

            $controller_class = false;
        }

        if (!$controller_class) {
            Console::stderr("Unknown command $this->controller\n", Console::FG_RED);
            return 1;
        }

        // Create a new instance of the controller
        $controller = new $controller_class;
        $controller->request = $this;

        return (int) $controller->_execute();
    }


    public function param($name, $default = null)
    {
        return $this->params[$name] ?? $default;
    }


    public function genHelp()
    {
        $namespaces = \array_unique(\array_merge(config('console.namespaces', [
            'app\\console',
            'mii\\console\\controllers',
        ]), ['mii\\console\\controllers']));


        $paths = \array_replace([
            'app\\console' => '@app/console',
            'mii\\console\\controllers' => __DIR__,//.'\\controllers'
        ], config('console.ns_paths', static::get_paths_from_composer($namespaces)));

        $list = [];
        foreach ($namespaces as $namespace) {
            if (!isset($paths[$namespace])) {
                Console::stderr("Dont know path for $namespace. Skip");
                continue;
            }

            $this->findControllers($namespace, \Mii::resolve($paths[$namespace]), $list);
        }

        Console::stdout("\n");

        $out = [];
        $max = 0;

        foreach ($list as ['class' => $class, 'command' => $command]) {
            $reflection = new \ReflectionClass($class);

            $doc = $reflection->getStaticPropertyValue('description', '');

            if (!$doc) {
                [$doc,] = static::getPhpdocSummary($reflection);
            }

            $max = \max($max, \mb_strlen($command));

            $result = [
                'command' => $command,
                'desc' => $doc,
                'actions' => [],
            ];

            $result['actions'] = static::getControllerActions($reflection);
            foreach ($result['actions'] as ['name' => $name]) {
                $max = \max($max, \mb_strlen($name));
            }

            $out[] = $result;
        }

        foreach ($out as ['command' => $command,
                 'desc' => $doc,
                 'actions' => $actions, ]) {
            Console::stdout(static::padded($command, $max), Console::FG_YELLOW);
            Console::stdout("$doc\n");

            foreach ($actions as ['name' => $action,
                     'summary' => $desc, ]) {
                Console::stdout(static::padded('  ' . $action, $max), Console::FG_GREEN);
                Console::stdout("$desc\n", Console::FG_GREY);
            }
            Console::stdout("\n");
        }
    }

    private static function padded(string $string, int $length)
    {
        return \str_pad($string, $length + 5);
    }

    /** @noinspection PhpIncludeInspection
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    private static function get_paths_from_composer($namespaces): ?array
    {
        $compdir = \realpath(__DIR__ . '/../../../../composer');
        if (!$compdir) {
            $compdir = path('root') . '/vendor/composer';
        }
        try {
            $loader = new \Composer\Autoload\ClassLoader();

            $map = require $compdir . '/autoload_namespaces.php';
            foreach ($map as $namespace => $path) {
                $loader->set($namespace, $path);
            }

            $map = require $compdir . '/autoload_psr4.php';

            foreach ($map as $namespace => $path) {
                $loader->setPsr4($namespace, $path);
            }

            $classMap = require $compdir . '/autoload_classmap.php';
            if ($classMap) {
                $loader->addClassMap($classMap);
            }

            $data = $loader->getPrefixesPsr4();
            $paths = [];


            foreach ($namespaces as $ns) {
                foreach ($data as $prefix => $path) {
                    if (\str_starts_with($ns, $prefix)) {
                        $path[0] .= \str_replace('\\', '/', \substr($ns, \strlen($prefix) - 1));
                        $paths[$ns] = $path[0];
                        break;
                    }
                }
            }

            return $paths;
        } catch (\Throwable $t) {
            Console::stderr(Exception::text($t));
            return [];
        }
    }

    protected function findControllers($namespace, $path, &$files)
    {
        $dir = \dir($path);
        while (false !== $entry = $dir->read()) {
            if ($entry === '.' || $entry === '..' || $entry === '.git' || \is_dir($dir->path . '/' . $entry)) {
                continue;
            }

            $info = \pathinfo($path . '/' . $entry);

            if (!isset($info['extension']) || $info['extension'] !== 'php') {
                continue;
            }

            if (!isset($files[$info['filename']])) {
                $files[$info['filename']] = [
                    'class' => $namespace . '\\' . $info['filename'],
                    'command' => \mb_strtolower($info['filename']),
                ];
            }
        }

        // Clean up
        $dir->close();
    }


    public static function getControllerActions($obj): array
    {
        $methods = $obj->getMethods(\ReflectionMethod::IS_PUBLIC);
        $results = [];

        /**
         * @var \ReflectionMethod $method
         */
        foreach ($methods as $method) {
            if (\in_array($method->name, ['__construct', 'index', '_execute'])) {
                continue;
            }

            $args = [];

            foreach ($method->getParameters() as $param) {
                $name = $param->getName();
                $optional = $param->isDefaultValueAvailable();

                $args[] = $optional ? '[' . $name . ']' : "<$name>";
            }

            [$summary, $desc] = static::getPhpdocSummary($obj->getMethod($method->name));

            $results[] = [
                'name' => $method->name,
                'summary' => $summary,
                'desc' => $desc,
                'args' => $args,
            ];
        }

        return $results;
    }

    /**
     * @param \Reflector $ref
     * @return array
     */
    public static function getPhpdocSummary(\Reflector $ref): array
    {
        $comment = $ref->getDocComment();

        if (!$comment) {
            return ['', ''];
        }

        $comment = \preg_replace('#[ \t]*(?:/\*\*|\*/|\*)?[ \t]?(.*)?#u', '$1', $comment);
        $comment = \trim($comment);

        if (str_ends_with($comment, '*/')) {
            $comment = \trim(\substr($comment, 0, -2));
        }

        $comment = \str_replace(["\r\n", "\r"], "\n", $comment);

        $lines = \explode("\n", $comment);

        $summary = $lines[0];
        $full = '';

        for ($i = 1; $i < \count($lines); $i++) {
            if (\str_starts_with(\trim($lines[$i]), '@')) {
                break;
            }
            $full .= $lines[$i] . "\n";
        }

        return [$summary, $full];
    }
}
