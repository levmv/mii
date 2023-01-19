<?php /** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);

namespace mii\console\controllers;

use Composer\InstalledVersions;
use Mii;
use mii\console\Controller;
use mii\core\Exception;
use mii\util\Console;
use mii\util\Debug;
use mii\util\Misc;
use mii\util\Text;

/**
 * Blocks assets builder
 *
 *  Global options:
 *
 *  --config=<path>       Path to configuration file.
 *                        By default, it's «@app/config/assets.php»
 *  --set=<setname>       Name of set to process
 *  --force               Don't check if files changed
 *  --minify              Post-process files with minifiers
 *                        (need mii-assets package)
 *  --only=<names>        Name or comma separated names of assets to process
 *  --skip=<names>        Name or comma separated names of assets to skip
 *  --json                To print assets paths as json
 *
 * @package mii\console\controllers
 */
enum AssetType: string
{
    case CSS = 'css';
    case JS = 'js';
    case ASSETS = 'assets';

    public function isFile(): bool
    {
        return $this !== self::ASSETS;
    }

    public function extension(): string
    {
        return "." . $this->value;
    }
}


class Assets extends Controller
{
    public string $config_file;
    private bool $json_output;
    private bool $force_mode;
    private array $filtered_sets;

    private array $assets;
    private array $libraries;

    private array $sets = [];

    private ?string $base_path = null;
    private string $base_url = '/assets';

    private string $assets_map_path;

    protected string $assets_group = 'static';

    private array $processed = [
        'css' => [],
        'js' => [],
    ];

    private float $_time;

    private string|bool $browserTargets = false;
    private bool $minify = false;
    private string $binPackagePath;
    private string $swcConfig;
    private bool $es5 = false;

    private array $skip = [];
    private array $only = [];


    protected function before()
    {
        $this->_time = \microtime(true);

        $this->sets = config('components.blocks.sets', [
            'default' => [
                'libraries' => [
                    '@app/blocks',
                ],
                'base_url' => '/assets',
            ],
        ]);

        $this->assets_map_path = config('components.blocks.assets_map_path', '@tmp');

        $params = $this->request->params;
        $this->config_file = $params['config'] ?? config('console.assets.config_file', '@app/config/assets.php');
        $this->json_output = $params['json'] ?? false;
        $this->force_mode = $params['force'] ?? false;
        $this->minify = $params['minify'] ?? false;
        $this->browserTargets = $params['browsers'] ?? false;
        $this->es5 = isset($params['es5']);

        $this->skip = $this->convToArray($params['skip'] ?? []);
        $this->only = $this->convToArray($params['only'] ?? []);

        $this->filtered_sets = (array)($this->request->params['set'] ?? \array_keys($this->sets));

        if (!\file_exists(Mii::resolve($this->config_file))) {
            $this->error("Config $this->config_file does not exist.");
            exit(1);
        }

        $this->assets = require Mii::resolve($this->config_file);

        if ($this->minify) {
            if (!InstalledVersions::isInstalled('levmv/mii-assets')) {
                $this->error("mii-assets are not installed. `minify` option disabled");
                $this->minify = false;
            } else {
                $this->binPackagePath = realpath(InstalledVersions::getInstallPath('levmv/mii-assets'));
                $this->swcConfig = file_exists(path('root') . "/.swcrc")
                    ? path('root') . "/.swcrc"
                    : $this->binPackagePath . "/.swcrc";
            }

            if ($this->json_output) {
                $this->warning("--json and --minify used together. Possible mistake? Disabled minify");
            }
        }
    }

    /**
     * Test assets config and show missed blocks list
     */
    public function test()
    {
        foreach ($this->sets as $name => $set) {
            $this->testSet($name);
        }
    }

    /**
     * Build assets
     */
    public function build()
    {
        foreach ($this->filtered_sets as $name) {

            $result = $this->buildSet($name);

            \file_put_contents(
                $this->assets_map_path . '/' . $name . '.assets',
                '<?php return ' . \var_export($result, true) . ';'
            );
        }

        if ($this->json_output) {
            Console::stdout(\json_encode($this->processed, Text::JSON_FLAGS));
            return;
        }

        if (\count($this->processed['css']) || \count($this->processed['js'])) {
            $this->info(':N css and :M js files compiled.', [
                ':N' => \count($this->processed['css']),
                ':M' => \count($this->processed['js']),
            ]);
        } else {
            $this->info('No recompiled files. No need to regenerate config');
        }

        $this->info("All done. Spent :Ts\n", [
            ':T' => \number_format(\microtime(true) - $this->_time, 2),
        ]);
    }


    protected function testSet(string $set_name): void
    {
        $this->info('==========================');
        $this->info('Testing of set «:name»', [':name' => $set_name]);
        $this->info('==========================');

        $this->initSet($set_name);

        $blocks = [];

        foreach ($this->libraries as $library) {
            if (!\is_dir($library)) {
                $this->error('Library path «' . $library . '» does not exist');
                return;
            }

            $directory = new \RecursiveDirectoryIterator($library);
            $filter = new \RecursiveCallbackFilterIterator($directory, static function ($current) {
                // Skip hidden files and directories.
                if ($current->getFilename()[0] === '.') {
                    return false;
                }
                if ($current->isDir()) {
                    // Only recurse into intended subdirectories.
                    return $current->getFilename() !== 'assets';
                }

                return $current->getExtension() !== 'php';
            });
            $iterator = new \RecursiveIteratorIterator($filter);

            foreach ($iterator as $info) {
                $type = $info->getExtension();
                $path = \trim(\substr($info->getPath(), \strlen($library)), '/');
                $block_name = \implode('_', \explode('/', $path));
                if (!isset($blocks[$block_name])) {
                    $blocks[$block_name] = ['css' => false, 'js' => false, 'path' => []];
                }
                $blocks[$block_name][$type] = true;
                $blocks[$block_name]['path'][] = $info->getPath();
            }
        }

        $reverse = ['css' => [], 'js' => []];

        if (!isset($this->assets[$this->assets_group])) {
            $this->error('Source list with name «' . $this->assets_group . '» does not exist');
            return;
        }

        foreach ($this->assets[$this->assets_group] as $name => $file) {
            foreach (['css', 'js'] as $type) {
                if (isset($file[$type])) {
                    if (!\is_array($file[$type])) {
                        $file[$type] = (array)$file[$type];
                    }

                    foreach ($file[$type] as $block) {
                        if (isset($reverse[$type][$block])) {
                            $this->error('Double declaration of :b (in «:f1» and «:f2» files)', [
                                ':b' => $block,
                                ':f1' => $reverse[$type][$block],
                                ':f2' => $name,
                            ]);
                        }
                        $reverse[$type][$block] = $name;

                        if (!isset($blocks[$block])) {
                            $this->error('Unknow block: :b in :f asset', [
                                ':b' => $block,
                                ':f' => $name . '.' . $type,
                            ]);
                        }
                    }
                }
            }
        }

        $forget = [];
        foreach ($blocks as $block_name => $block) {
            foreach (['css', 'js'] as $type) {
                if ($block[$type] && !isset($reverse[$type][$block_name])) {
                    $forget[$block_name][] = "'" . $type . "' => '" . $block_name . "'";
                }
            }
        }

        if (\count($forget)) {
            $this->warning('May be you forget these:');
            foreach ($forget as $name => $block) {
                $out = "'$name' => [";
                $out .= \implode(',', $block);

                $this->stdout("$out],\n");
            }
        }
    }


    protected function initSet(string $set_name): void
    {
        $this->base_path = null;
        $this->libraries = [];

        $default_set = [
            'libraries' => [],
            'base_url' => $this->base_url,
            'base_path' => $this->base_path,
        ];

        if (!isset($this->sets[$set_name]) && $set_name === 'default') {
            $set = [];
        } elseif (isset($this->sets[$set_name])) {
            $set = $this->sets[$set_name];
        } else {
            throw new Exception("Unknow blocks set name: $set_name");
        }

        $set = \array_replace_recursive($default_set, $set);

        foreach ($set as $key => $value) {
            $this->$key = $value;
        }

        for ($i = 0; $i < \count($this->libraries); $i++) {
            $this->libraries[$i] = Mii::resolve($this->libraries[$i]);
        }

        $this->base_url = Mii::resolve($this->base_url);

        if ($this->base_path === null) {
            $this->base_path = isset(Mii::$paths['pub'])
                ? '@pub' . $this->base_url
                : '@root/public' . $this->base_url;
        }

        $this->base_path = rtrim(Mii::resolve($this->base_path), '/') . '/';

        $this->assets_map_path = Mii::resolve($this->assets_map_path);

        if (!\is_dir($this->base_path)) {
            Misc::mkdir($this->base_path, 0775, true);
        }

        if (!isset($this->assets[$this->assets_group])) {
            $this->error('Assets group «' . $this->assets_group . '» does not exist');
        }
    }


    protected function buildSet(string $set_name): array
    {
        $this->initSet($set_name);

        $this->info("Build $set_name set");

        $results = [];
        foreach ($this->assets[$this->assets_group] as $filename => $data) {
            if (in_array($filename, $this->skip)) {
                continue;
            }

            if (!empty($this->only) && !in_array($filename, $this->only)) {
                continue;
            }

            $usedBlocks = [];

            foreach ([AssetType::CSS, AssetType::JS] as $type) {
                if (!isset($data[$type->value])) {
                    continue;
                }
                $blocks = $data[$type->value];

                if (!\is_array($blocks)) {
                    $blocks = [$blocks];
                }

                $files = [];
                foreach ($this->iterateBlocks($blocks, $type) as $file) {
                    $files[] = $file;
                }

                if (empty($files)) {
                    $this->warning("Files to build $type->value for \"$filename\" not found");
                    continue;
                }

                $result_file_name = $filename.$this->calculateHashName($files);

                // In results we store reverse list: block_name => output file
                foreach ($blocks as $block_name) {
                    $usedBlocks[$block_name] = true;
                    $results[$type->value][$block_name] = $result_file_name;
                }

                $fullResultPath = $this->base_path . $result_file_name . $type->extension();

                if (!\file_exists($fullResultPath) || $this->force_mode) {
                    $this->mergeFilesToOne($files, $fullResultPath);
                    $this->processed[$type->value][] = $fullResultPath;

                    $this->stdout(Debug::path($fullResultPath));
                    $this->stdout(" merged ", Console::FG_GREEN);

                    if ($this->minify && $this->minify($type, $fullResultPath)) {
                        $this->stdout("& minified ", Console::FG_GREEN);
                    }
                    $this->gzipFile($fullResultPath);

                    $this->stdout("\n");
                }
            }
            $this->linkAssets($filename, array_keys($usedBlocks));
        }
        return $results;
    }


    protected function calculateHashName(array $files): string
    {
        // Yep, little paranoid, hence filesize :)
        $hashesStr = array_reduce($files,
            fn($carry, $file) => $carry .= hash_file('sha256', $file, true) . pack('L', \filesize($file))
        );

        return '.' . Text::b64Encode(
                \substr(\md5($hashesStr, true), 0, 3) .
                \substr(\sha1($hashesStr, true), 0, 3)
            );
    }


    // TODO: do we need unlinking?
    protected function linkAssets($filename, $blocks)
    {
        foreach ($this->iterateBlocks($blocks, AssetType::ASSETS) as $blockName => $assetsDirPath) {
            $output = $this->base_path . $blockName;

            if (!is_link($output)) {
                symlink($assetsDirPath, $output);
            }
        }
    }

    protected function iterateBlocks(array $blockNames, AssetType $type): \Generator
    {
        foreach ($blockNames as $block_name) {
            $blockPath = $this->nameToPath($block_name);

            foreach ($this->libraries as $libPath) {
                if ($type->isFile()) {
                    $result_filename = $libPath . $blockPath . $block_name . $type->extension();
                    if (\is_file($result_filename)) {
                        yield $result_filename;
                        break;
                    }
                } else {
                    if (\is_dir($libPath . $blockPath . $type->value)) {
                        yield $block_name => $libPath . $blockPath . $type->value;
                        break;
                    }
                }
            }
        }
    }

    protected function nameToPath(string $name): string
    {
        return '/' . \implode('/', \explode('_', $name)) . '/';
    }

    protected function stdout($string, ...$args)
    {
        if ($this->json_output) {
            return;
        }
        return parent::stdout($string, ...$args);
    }

    protected function warning($msg, $options = []): void
    {
        if ($this->json_output) {
            parent::error($msg, $options);
            return;
        }
        parent::warning($msg, $options); // TODO: Change the autogenerated stub
    }


    protected function mergeFilesToOne(array $files, string $path): void
    {
        $tmp = '';
        foreach ($files as $file) {
            $tmp .= \file_get_contents($file) . "\n";
        }

        $written = \file_put_contents($path, $tmp);
        if ($written === 0) {
            $this->warning("$path has zero size");
        }
    }


    protected function gzipFile(string $path): void
    {
        file_put_contents($path . ".gz", gzencode(file_get_contents($path), 7));
    }


    protected function minify(AssetType $type, string $file): bool
    {
        return match ($type) {
            AssetType::CSS => $this->cssProcess($file),
            AssetType::JS => $this->jsProcess($file),
            AssetType::ASSETS => throw new \Exception('To be implemented')
        };
    }


    protected function cssProcess(string $file): bool
    {
        $command = "lightningcss --minify";

        if ($this->browserTargets) {
            $command .= " --targets '{$this->browserTargets}'";
        }
        $command .= " '$file' --output-file='$file'";

        return $this->executeBinary($command);
    }


    protected function jsProcess(string $file): bool
    {
        $command = "esbuild --minify '$file' --target=es6 --allow-overwrite --log-level=warning --outfile='$file'";

        $result = $this->executeBinary($command);

        return ($this->es5)
            ? $this->es5Process($file)
            : $result;
    }

    protected function es5Process(string $file): bool
    {
        $command = "swc compile '$file' --config-file={$this->swcConfig} --out-file='$file'";

        if ($this->browserTargets) {
            $command .= " --config env.targets=\"{$this->browserTargets}\"";
        }
        return $this->executeBinary($command);
    }


    protected function executeBinary(string $command): bool
    {
        $output = [];
        $status = 0;

        exec($this->binPackagePath . "/bin/$command", $output, $status); // may be 2>&1 ?

        if ($output) {
            $this->error("\n" . implode("\n", $output));
        }
        return $status === 0;
    }


    /**
     * Converts argument to one flat array:
     * "string" => ["string"]
     * ["a,b","c,d"] => ["a","b","c","d"]
     * "a,b,c" => ["a","b","c"]
     */
    protected function convToArray(array|string $array): array
    {
        if (is_string($array)) {
            $array = [$array];
        }
        $result = [];
        foreach ($array as $el) {
            if (is_string($el)) {
                $el = explode(',', $el);
            }
            foreach ($el as $name) {
                $result[] = trim($name);
            }
        }

        return $result;
    }
}
