<?php declare(strict_types=1);

namespace mii\web;

use Mii;
use mii\core\Component;

abstract class BaseBlocks extends Component
{
    public const HEAD = 0;
    public const BEGIN = 1;
    public const END = 2;

    protected string $base_url = '/assets';

    protected $current_set;

    protected array $sets = [];

    protected array $_blocks = [];

    protected $libraries;

    protected bool $_rendered = false;

    protected array $_css = [];

    protected array $_js = [[], [], []];

    protected array $_used_blocks = [];

    public function init(array $config = []): void
    {
        parent::init($config);

        if (empty($this->sets)) {
            $this->sets['default'] = [
                'libraries' => [
                    '@app/blocks'
                ],
                'base_url' => '/assets'
            ];
        }

        $this->load_set(\key($this->sets));
    }


    public function load_set(string $setname): void
    {
        assert(isset($this->sets[$setname]), "Unknown blocks set name: $setname");

        $this->current_set = $setname;

        $set = \array_replace([
            'libraries' => [],
            'base_url' => $this->base_url,
            'base_path' => null
        ], $this->sets[$setname]);

        foreach ($set as $key => $value)
            $this->$key = $value;

        for ($i = 0, $imx = \count($this->libraries); $i < $imx; $i++)
            $this->libraries[$i] = Mii::resolve($this->libraries[$i]);
    }

    /**
     * Create a new block, or get an existing block
     * @static
     * @param $name string Block name
     * @return Block
     */
    public function get(string $name): Block
    {
        if (!isset($this->_blocks[$name])) {
            $this->_blocks[$name] = new Block($name);
        }

        return $this->_blocks[$name];
    }


    public function get_block_php_file(string $name): ?string
    {
        $path = \implode('/', \explode('_', $name));
        $block_path = "/$path/$name.php";

        foreach ($this->libraries as $library_path) {
            if (\is_readable($library_path . $block_path)) {
                return $library_path . $block_path;
            }
        }

        return null;
    }

    public function css(): string
    {
        if (!$this->_rendered)
            $this->render();

        return implode("\n", $this->_css);
    }


    public function js(int $position = null): string
    {
        if (!$this->_rendered)
            $this->render();

        if ($position === null) {
            $out = [];
            foreach ($this->_js as $js) {
                if (!empty($js))
                    $out[] = \implode("\n", $js);
            }
            return \implode("\n", $out);
        }

        return \implode("\n", $this->_js[$position]);
    }


    abstract public function render(): void;

}

