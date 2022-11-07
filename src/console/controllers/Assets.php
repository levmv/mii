<?php /** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);

namespace mii\console\controllers;

use Composer\InstalledVersions;
use Mii;
use mii\console\Controller;
use mii\core\Exception;
use mii\util\Debug;
use mii\util\Misc;
use mii\util\Text;

/**
 * Blocks assets builder
 *
 *  Global options:
 *
 *  --config=<path>  Path to configuration file. By default, it's «@app/config/assets.php»
 *  --set=<setname> Name of set to process
 *  --force         Don't check if files changed
 *  --minify        Post-process files with minifiers (need mii-assets package)
 *  --gzip          Make gzipped version of files
 *  --json          To print assets paths as json
 *
 * @package mii\console\controllers
 */
class Assets extends Controller
{
    public string $config_file;
    private bool $json_output;
    private bool $force_mode;
    private array $filtered_sets;

    private array $assets;
    private array $libraries;

    private array $sets = [];

    private ?string $base_path;
    private string $base_url = '/assets';

    private string $assets_map_path;

    protected string $assets_group = 'static';

    private array $results = [];

    private array $processed = [
        'css' => [],
        'js' => [],
    ];

    private float $_time;
    private int $_memory;

    private bool $gzip = false;

    private string|bool $browserTargets = false;
    private bool $minify = false;
    private string $binPackagePath;
    private string $swcConfig;


    protected function before()
    {
        $this->_time = \microtime(true);
        $this->_memory = \memory_get_usage();

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
        $this->gzip = $params['gzip'] ?? false;
        $this->browserTargets = $params['browsers'] ?? false;

        $this->filtered_sets = (array) ($this->request->params['set'] ?? \array_keys($this->sets));

        if (!\file_exists(Mii::resolve($this->config_file))) {
            $this->error("Config $this->config_file does not exist.");
            exit(1);
        }

        $this->assets = require Mii::resolve($this->config_file);

        if($this->minify) {
            if(!InstalledVersions::isInstalled('levmv/mii-assets')) {
                $this->error("mii-assets are not installed. `minify` option disabled");
                $this->minify = false;
            } else {
                $this->binPackagePath = realpath(InstalledVersions::getInstallPath('levmv/mii-assets'));
                $this->swcConfig = file_exists(path('root')."/.swcrc")
                    ? path('root')."/.swcrc"
                    : $this->binPackagePath."/.swcrc";
            }
        }
    }

    /**
     * Test assets config and show missed blocks list
     */
    public function test()
    {
        if (\count($this->sets)) {
            foreach ($this->sets as $name => $set) {
                $this->testSet($name);
            }
        } else {
            $this->testSet('default');
        }
    }

    /**
     * Build assets
     */
    public function build()
    {
        if (\count($this->sets)) {
            foreach ($this->filtered_sets as $name) {
                $this->buildSet($name);
            }
        } else {
            $this->buildSet('default');
        }

        foreach ($this->results as $set_name => $list) {
            \file_put_contents(
                $this->assets_map_path . '/' . $set_name . '.assets',
                '<?php return ' . \var_export($list, true) . ';'
            );
        }

        if ($this->json_output) {
            $this->stdout(\json_encode($this->processed, Text::JSON_FLAGS));
            return;
        }

        if($this->minify) {
            foreach($this->processed['css'] as $css) {
                $this->cssProcess($css);
                $this->gzipFile($css);
            }
            foreach($this->processed['js'] as $js) {
                $this->jsProcess($js);
                $this->gzipFile($js);
            }
        }


        if (\count($this->processed['css']) || \count($this->processed['js'])) {
            $this->info(':N css and :M js files compiled.', [
                ':N' => \count($this->processed['css']),
                ':M' => \count($this->processed['js']),
            ]);
        } else {
            $this->info('No recompiled files. No need to regenerate config');
        }

        $this->info('All done. Spent :Ts and :MMb', [
            ':T' => \number_format(\microtime(true) - $this->_time, 2),
            ':M' => \number_format((\memory_get_usage() - $this->_memory) / 1024 / 1024, 1),
        ]);
    }


    protected function testSet($set_name): void
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
                        $file[$type] = (array) $file[$type];
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


    protected function initSet($set_name): void
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

        $this->base_path = Mii::resolve($this->base_path);

        $this->assets_map_path = Mii::resolve($this->assets_map_path);

        if (!\is_dir($this->base_path)) {
            Misc::mkdir($this->base_path, 0777);
        }

        if (!isset($this->assets[$this->assets_group])) {
            $this->error('Assets group «' . $this->assets_group . '» does not exist');
        }
    }


    protected function buildSet($set_name): void
    {
        $this->initSet($set_name);

        $this->results[$set_name] = [];
        foreach ($this->assets[$this->assets_group] as $filename => $data) {
            foreach (['css', 'js'] as $type) {
                if (!isset($data[$type])) {
                    continue;
                }

                if (!\is_array($data[$type])) {
                    $data[$type] = (array) $data[$type];
                }

                $result_file_name = $this->buildFile($filename, $data[$type], $type);

                if(isset($data['block_name'])) {
                    $this->results[$set_name][$type][$data['block_name']] = $result_file_name;
                } else {
                    foreach ($data[$type] as $block_name) {
                        $this->results[$set_name][$type][$block_name] = $result_file_name;
                    }
                }
            }
        }
    }


    protected function buildFile($filename, $blocks, $type): string
    {
        $out_path = $this->base_path . '/';

        $files = [];

        $hashes = '';

        foreach ($blocks as $block_name) {
            $block_path = '/' . \implode('/', \explode('_', $block_name));
            $block_file = $block_path . '/' . $block_name;

            foreach ($this->libraries as $library_path) {
                $result_filename = Mii::resolve($library_path) . $block_file . '.' . $type;

                if (\is_file($result_filename)) {
                    $files[] = $result_filename;
                    $hashes .= \hash_file('sha256', $result_filename, true) . \pack('L', \filesize($result_filename));

                    break;
                }
            }
            // Can't merge with previous cycle because of the break
            foreach ($this->libraries as $library_path) {
                $library_path = Mii::resolve($library_path);

                if (\is_dir($library_path . $block_path . '/assets')) {
                    $output = $this->base_path . '/' . $block_name;

                    if (!\is_link($output)) {
                        \symlink($library_path . $block_path . '/assets', $output);
                    }
                    break;
                }
            }
        }

        $outname = $filename . $this->hash($hashes);

        if (!empty($files)) {
            if (!\file_exists($out_path . $outname . '.' . $type) || $this->force_mode) {
                $this->processed[$type][] = $this->mergeFilesToOne($files, $out_path, $outname . '.' . $type);
            }
        }

        return $outname;
    }


    protected function hash(string $str): string
    {
        return '.'.Text::b64Encode(
                \substr(\md5($str, true), 0, 6) .
                \substr(\sha1($str, true), 0, 1)
            );
    }


    protected function mergeFilesToOne($files, $path, $filename)
    {
        $tmp = '';

        foreach ($files as $file) {
            $tmp .= \file_get_contents($file) . "\n";
        }

        if (!\is_dir($path)) {
            Misc::mkdir($path, 0777, true);
        }

        \file_put_contents($path . $filename, $tmp);

        if (!$this->json_output) {
            $this->info(':block compiled.', [':block' => Debug::path($path) . $filename]);
        }

        return $path . $filename;
    }


    protected function gzipFile(string $path): void
    {
        file_put_contents($path.".gz", gzencode(file_get_contents($path), 7));
    }


    protected function cssProcess($file): bool
    {
        $command = "lightningcss --minify";

        if($this->browserTargets) {
            $command .= " --targets '{$this->browserTargets}'";
        }

        $command .= " '$file' --output-file='$file'";

        return $this->executeBinary($command);
    }


    protected function jsProcess($file): bool
    {
        $command = "swc compile '$file' --config-file={$this->swcConfig} --out-file='$file'";

        if($this->browserTargets) {
            $command .= " -C env.targets={$this->browserTargets}";
        }

        return $this->executeBinary($command);
    }


    protected function executeBinary(string $command): bool {
        $output = '';
        $status = 0;

        exec($this->binPackagePath."/bin/$command", $status, $output);

        if($output) {
            echo $output;
        }

        return $status === 0;
    }
}
