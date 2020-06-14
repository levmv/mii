<?php declare(strict_types=1);

namespace mii\core;


class Component
{

    public function __construct(array $config = [])
    {
        $this->init($config);
    }


    public function init(array $config = []): void
    {
        foreach ($config as $key => $value)
            $this->$key = $value;
    }

}
