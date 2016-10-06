<?php

namespace mii\console;


class App extends \mii\core\App
{

    protected $_blocks;

    protected $_session;


    public function run()
    {
        try {
            $this->request = new Request();
            $this->request->execute()->send();

        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function default_components() {
        return array_merge(parent::default_components(), [
            'request' => ['class' => 'mii\console\Request'],
            'response' => ['class' => 'mii\console\Response'],
            'error' => ['class' => 'mii\console\ErrorHandler']
        ]);
    }


}