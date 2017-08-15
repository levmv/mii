<?php

namespace mii\core;


class Component
{

    protected $component_id = '';

    public function __construct($config = [], $id = null) {
        $this->init($config);

        $this->component_id = $id;
    }


    public function init(array $config = []): void {
        foreach ($config as $key => $value)
            $this->$key = $value;
    }

}