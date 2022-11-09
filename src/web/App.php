<?php declare(strict_types=1);

namespace mii\web;

use mii\core\InvalidRouteException;
use mii\core\Router;

/**
 * Class App
 *
 * @property Session       $session
 * @property Request       $request
 * @property Blocks        $blocks
 * @property Router        $router
 * @property Response      $response
 * @property UploadHandler $upload
 *
 */
class App extends \mii\core\App
{
    /**
     * @throws InvalidRouteException|\JsonException
     */
    public function run(): void
    {
        if(!$this->request->setUri($_SERVER['REQUEST_URI'], $this->base_url)) { // TODO: not the best place for this?
            throw new InvalidRouteException();
        }

        if (false === ($params = $this->router->match($this->request->uri()))) {
            throw new InvalidRouteException();
        }

        $this->request->params = $params;

        $controller = new $params['controller'];
        $controller->request = $this->request;
        $controller->response = $this->response;
        try {
            $controller->execute($params['action'], $params);
        } catch (RedirectHttpException $e) {
            $this->response->redirect($e->url);
        }

        $this->response->send();
    }


    protected function defaultComponents(): array
    {
        return [
            'log' => 'mii\log\Logger',
            'blocks' => 'mii\web\Blocks',
            'db' => 'mii\db\Database',
            'cache' => 'mii\cache\Apcu',
            'session' => 'mii\web\Session',
            'router' => 'mii\core\Router',
            'request' => 'mii\web\Request',
            'response' => 'mii\web\Response',
            'upload' => 'mii\web\UploadHandler',
            'error' => 'mii\web\ErrorHandler',
        ];
    }
}
