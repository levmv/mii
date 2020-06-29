<?php declare(strict_types=1);

namespace mii\web;

use mii\core\InvalidRouteException;

class Controller
{

    /**
     * @var  Request  Request that created the controller
     */
    public Request $request;

    /**
     * @var  Response The response that will be returned from controller
     */
    public Response $response;


    protected function before()
    {
    }

    protected function after($content = null)
    {
        $this->response->content($content);
    }

    public function execute(string $action, $params): void
    {
        if (!\method_exists($this, $action)) {
            throw new InvalidRouteException('Method "' . \get_class($this) . "::$action\" does not exists");
        }

        $method = new \ReflectionMethod($this, $action);

        if (!$method->isPublic()) {
            throw new BadRequestHttpException('Cannot access not public method');
        }

        $this->before();

        $this->after($this->executeAction($method, $action, $params));
    }


    protected function executeAction(\ReflectionMethod $method, string $action, $params)
    {
        $args = [];
        $missing = [];
        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (\array_key_exists($name, $params)) {
                $is_valid = true;

                if ($param->isArray()) {
                    $params[$name] = (array) $params[$name];
                } elseif (\is_array($params[$name])) {
                    $is_valid = false;
                } elseif (
                    ($type = $param->getType()) !== null &&
                    $type->isBuiltin() &&
                    ($params[$name] !== null || !$type->allowsNull())
                ) {
                    $type_name = $type->getName();
                    switch ($type_name) {
                        case 'int':
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
                        $is_valid = false;
                    }
                }
                if (!$is_valid) {
                    throw new BadRequestHttpException("Invalid data received for parameter \"$name\".");
                }
                $args[] = $params[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $missing[] = $name;
            }
        }
        if (!empty($missing)) {
            throw new BadRequestHttpException('Missing required parameters: "' . \implode(', ', $missing) . '"');
        }

        return \call_user_func_array([$this, $action], $args);
    }
}
