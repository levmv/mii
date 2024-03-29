<?php declare(strict_types=1);

namespace mii\web;

use mii\core\InvalidRouteException;

class Controller
{

    /**
     * @var  Request  Request that created the controller
     * @deprecated
     */
    public Request $request;

    /**
     * @var  Response The response that will be returned from controller
     */
    public Response $response;


    protected function before(): void
    {
    }

    protected function after($content = null): void
    {
        \Mii::$app->response->content($content);
    }

    /**
     * @throws InvalidRouteException
     * @throws BadRequestHttpException
     */
    public function execute(string $action, $params): void
    {
        if (!\method_exists($this, $action)) {
            throw new InvalidRouteException('Method "' . static::class . "::$action\" does not exists");
        }

        $method = new \ReflectionMethod($this, $action);

        if (!$method->isPublic()) {
            throw new BadRequestHttpException('Cannot access not public method');
        }

        $returnType = $method->getReturnType();

        if ($returnType instanceof \ReflectionNamedType && $returnType->getName() === 'array') {
            \Mii::$app->response->format = Response::FORMAT_JSON;
        }

        $this->before();

        $this->after($this->executeAction($method, $action, $params));
    }

    // TODO: rewrite that simplier?
    protected function executeAction(\ReflectionMethod $method, string $action, $params)
    {
        $args = [];
        $missing = [];
        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            /**
             * Yeah, we silently don't support Intersection|Union types O_o
             * @var \ReflectionNamedType|null $type
             */
            $type = $param->getType();

            if (isset($params[$name])) {
                $isValid = true;

                if ($type !== null && $type->getName() === 'array') {
                    $params[$name] = (array)$params[$name];
                } elseif (\is_array($params[$name])) {
                    $isValid = false;
                } elseif ($type !== null) {
                    if ($type->isBuiltin() && ($params[$name] !== null || !$type->allowsNull())) {
                        $type_name = $type->getName();
                        switch ($type_name) {
                            case 'int':
                                if (\strlen((string)$params[$name]) > 1 && $params[$name][0] === '0') {
                                    $params[$name] = \substr($params[$name], 1);
                                }
                                $params[$name] = \filter_var($params[$name], \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE);
                                break;
                            case 'float':
                                $params[$name] = \filter_var($params[$name], \FILTER_VALIDATE_FLOAT, \FILTER_NULL_ON_FAILURE);
                                break;
                            case 'bool':
                                $params[$name] = \filter_var($params[$name], \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
                                break;
                        }
                        if ($params[$name] === null) {
                            $isValid = false;
                        }
                    }
                }
                if (!$isValid) {
                    throw new BadRequestHttpException("Invalid data received for parameter \"$name\".");
                }
                $args[] = $params[$name];
            } else {
                if (($type === null || $type->isBuiltin()) && $param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } elseif ($type !== null) {
                    $name = $type->getName();
                    if (!$type->isBuiltin() && \Mii::$app->hasClass($name)) {
                        $args[] = \Mii::$app->getByClass($name);
                    } else {
                        $missing[] = $name;
                    }
                } else {
                    $missing[] = $name;
                }
            }
        }
        if (!empty($missing)) {
            throw new BadRequestHttpException('Missing required parameters: "' . \implode(', ', $missing) . '"');
        }

        return \call_user_func_array([$this, $action], $args);
    }


    protected function input(string $name, $default = null)
    {
        return $_POST[$name] ?? $_GET[$name] ?? $default;
    }

    protected function allowOnlyPost(): void
    {
        if (!$this->request->isPost()) {
            throw new BadRequestHttpException;
        }
    }

    protected function jsonResponseFormat(): void
    {
        $this->response->format = Response::FORMAT_JSON;
    }
}
