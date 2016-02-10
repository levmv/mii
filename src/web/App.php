<?php

namespace mii\web;

use mii\captcha\Captcha;

/**
 * Class App
 *
 * @property Session $session The session component.
 * @property \mii\auth\User $user The user component.
 * @property Request $request
 * @property Blocks $blocks
 * @property Router $router
 * @property Response $response
 * @property Captcha $captcha
 *
 */
class App extends \mii\core\App
{
    public $user;

    public function run()
    {
        $this->request->execute()->send();

    }

    public function default_components() {
        return array_merge(parent::default_components(), [
            'session' => ['class' => 'mii\web\Session'],
            'blocks' => ['class' => 'mii\web\Blocks'],
            'router' => ['class' => 'mii\web\Router'],
            'request' => ['class' => 'mii\web\Request'],
            'response' => ['class' => 'mii\web\Response'],
            'captcha' => ['class' => 'mii\captcha\Captcha'],
            'error' => ['class' => 'mii\web\ErrorHandler']
        ]);
    }



}