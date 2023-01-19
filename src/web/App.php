<?php declare(strict_types=1);

namespace mii\web;

use mii\core\InvalidRouteException;
use mii\core\Router;
use mii\log\Logger;
use mii\web\Blocks;
use mii\db\Database;
use mii\cache\Apcu;
use mii\web\Session;
use mii\web\Request;
use mii\web\Response;
use mii\web\UploadHandler;
use mii\web\ErrorHandler;

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
    public function run(string $uri = null): void
    {
        if($uri === null) {
            $uri = $_SERVER['REQUEST_URI'];
        }
        if(!$this->request->setUri($uri, $this->base_url)) { // TODO: not the best place for this?
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
            'log' => Logger::class,
            'blocks' => Blocks::class,
            'db' => Database::class,
            'cache' => Apcu::class,
            'session' => Session::class,
            'router' => Router::class,
            'request' => Request::class,
            'response' => Response::class,
            'upload' => UploadHandler::class,
            'error' => ErrorHandler::class,
        ];
    }
}
