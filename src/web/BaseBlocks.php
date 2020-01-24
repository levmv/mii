<?php

namespace mii\web;

use Mii;
use mii\core\Component;

class BaseBlocks extends Component
{
    const HEAD = 0;
    const BEGIN = 1;
    const END = 2;

    protected $base_url = '/assets';

    protected $current_set;

    protected $sets = [];

    protected $_blocks = [];

    protected $libraries;

    protected $_rendered = false;

    protected $_css = [];

    protected $_js = [[], [], []];

    protected $_used_blocks = [];

    protected $_files = [
        'css' => [
        ],
        'js' => [
        ],
    ];

    protected $assets;


    public function init(array $config = []): void {
        parent::init($config);

        if (empty($this->sets)) {
            $this->sets[] = [
                'libraries' => [
                    '@app/blocks'
                ],
                'base_url' => '/assets'
            ];
        }

        $this->load_set(\key($this->sets));
    }


    public function load_set(string $setname): void {

        assert(isset($this->sets[$setname]), "Unknown blocks set name: $setname");

        $this->current_set = $setname;

        $set = \array_replace([
            'libraries' => [],
            'base_url' => $this->base_url,
            'base_path' => null
        ], $this->sets[$setname]);

        foreach ($set as $key => $value)
            $this->$key = $value;

        for ($i = 0; $i < \count($this->libraries); $i++)
            $this->libraries[$i] = Mii::resolve($this->libraries[$i]);
    }

    /**
     * Create a new block, or get an existing block
     * @static
     * @param $name string Block name
     * @return Block
     */
    public function get(string $name): Block {
        if (!isset($this->_blocks[$name])) {
            $this->_blocks[$name] = new Block($name);
        }

        return $this->_blocks[$name];
    }


    public function get_block_php_file(string $name): ?string {

        $block_file = null;
        $block_path = $this->get_block_path($name).$name;

        foreach ($this->libraries as $library_path) {
            if (\is_readable($library_path . $block_path . '.php')) {
                return $library_path . $block_path . '.php';
            }
        }

        return null;
    }

    public function get_block_path(string $name): ?string {
        return '/' . \implode('/', \explode('_', $name)) . '/';
    }


    public function css(): string {
        if (!$this->_rendered)
            $this->render();

        return implode("\n", $this->_css);
    }


    public function js(int $position = null): string {
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


    public function render(): void {}


    public function assets_path_by_name($block_name) {
        $block_path = $this->get_block_path($block_name);

        foreach ($this->libraries as $base_path) {

            if (\is_dir($base_path . $block_path . 'assets')) {
                return $base_path . $block_path . 'assets';
            }
        }
        return false;
    }


}

