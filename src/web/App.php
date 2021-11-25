<?php declare(strict_types=1);

namespace mii\web;

use mii\core\InvalidRouteException;
use mii\core\Router;

/**
 * Class App
 *
 * @property Session       $session The session component.
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
        $params = $this->router->match($this->request->uri());

        if ($params === false) {
            throw new InvalidRouteException();
        }

        $this->request->controller = $controller_name = $params['controller'];

        $this->request->action = $params['action'];

        // These are accessible as public vars and can be overloaded
        unset($params['controller'], $params['action']);

        // Params cannot be changed once matched
        $this->request->params = $params;

        // Create a new instance of the controller
        $this->controller = new $controller_name;

        // Save links to request and response just for usability
        $this->controller->request = $this->request;
        $this->controller->response = $this->response;
        try {
            $this->controller->execute($this->request->action, $params);
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
