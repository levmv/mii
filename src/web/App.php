<?php

namespace mii\web;

use mii\core\InvalidRouteException;
use mii\core\Router;

/**
 * Class App
 *
 * @property Session $session The session component.
 * @property \mii\auth\User $user The user component.
 * @property Request $request
 * @property Blocks $blocks
 * @property Router $router
 * @property Response $response
 * @property UploadHandler $upload
 *
 */
class App extends \mii\core\App
{
    public $user;

    public $request;

    public $response;

    public $maintenance;
    public $maintenance_message;

    public function run() {

        $this->request = $this->get('request');
        $this->response = $this->get('response');

        if ($this->maintenance) {

            $this->response->status(503);
            $this->response->add_header('Retry-After', 30);
            $this->response->content(
                empty($this->maintenance_message)
                    ? 'На сайте технические работы, которые закончатся через несколько секунд. Пожалуйста, обновите страницу в браузере'
                    : $this->maintenance_message
            );

            $this->response->send();
            die;
        }

        try {

            $uri = $this->request->uri();

            $params = $this->router->match($uri);

            if ($params === false) {
                throw new InvalidRouteException('Unable to find a route to match the URI: :uri', [
                    ':uri' => $uri
                ]);
            }

            $this->request->controller = $controller_name = $params['controller'];

            $this->request->action = $params['action'];

            // These are accessible as public vars and can be overloaded
            unset($params['controller'], $params['action']);

            // Params cannot be changed once matched
            $this->request->params = $params;

            // Create a new instance of the controller

            if ($this->container === null) {
                $this->controller = new $controller_name;
            } else {
                $this->controller = $this->container->get($controller_name);
            }

            // Save links to request and response just for usability
            $this->controller->request = $this->request;
            $this->controller->response = $this->response;

            $this->controller->execute($this->request->action, $params);


        } catch (RedirectHttpException $e) {

            $this->response->redirect($e->url);

        } catch (InvalidRouteException $e) {
            if (config('debug')) {
                throw $e;
            } else {
                throw new NotFoundHttpException();
            }
        } catch (ForbiddenHttpException $e) {
            if (config('debug')) {
                throw $e;
            } else {
                throw new NotFoundHttpException();
            }
        }

        $this->response->send();
    }


    public function default_components() : array {
        return [
            'log' => ['class' => 'mii\log\Logger'],
            'blocks' => ['class' => 'mii\web\Blocks'],
            'auth' => ['class' => 'mii\auth\Auth'],
            'db' => ['class' => 'mii\db\Database'],
            'cache' => ['class' => 'mii\cache\Apcu'],
            'mailer' => ['class' => 'mii\email\PHPMailer'],
            'session' => ['class' => 'mii\web\Session'],
            'router' => ['class' => 'mii\core\Router'],
            'request' => ['class' => 'mii\web\Request'],
            'response' => ['class' => 'mii\web\Response'],
            'upload' => ['class' => 'mii\web\UploadHandler'],
            'error' => ['class' => 'mii\web\ErrorHandler']
        ];
    }


}