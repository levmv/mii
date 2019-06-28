<?php

namespace mii\web;

use Mii;

class Controller
{

    /**
     * @var  Request  Request that created the controller
     */
    public $request;

    /**
     * @var  Response The response that will be returned from controller
     */
    public $response;


    protected function before() {

    }

    protected function after($content = null) {
        Mii::$app->response->content($content);
    }

    public function execute(string $action, $params) {

        $method = new \ReflectionMethod($this, $action);

        if (!$method->isPublic())
            throw new BadRequestHttpException("Cannot access not public method");

        $this->before();

        $this->after($this->execute_action($method, $action, $params));
    }


    protected function execute_action($method, $action, $params) {
        $args = [];
        $missing = [];
        $action_params = [];
        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (\array_key_exists($name, $params)) {
                if ($param->isArray()) {
                    $args[] = $action_params[$name] = is_array($params[$name]) ? $params[$name] : [$params[$name]];
                } elseif (!is_array($params[$name])) {
                    $args[] = $action_params[$name] = $params[$name];
                } else {
                    throw new HttpException(500, 'Invalid data received for parameter ":param".', [
                        ':param' => $name,
                    ]);
                }
                unset($params[$name]);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $action_params[$name] = $param->getDefaultValue();
            } else {
                $missing[] = $name;
            }
        }
        if (!empty($missing)) {
            throw new HttpException(500, 'Missing required parameters: ":params"', [
                ':params' => implode(', ', $missing),
            ]);
        }
        $this->action_params = $action_params;

        return call_user_func_array([$this, $action], $args);
    }


}