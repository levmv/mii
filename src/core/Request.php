<?php

namespace mii\core;


abstract class Request {

    /**
     * @var  Route       route matched for this request
     */
    protected $_route;

    /**
     * @var  Route       array of routes to manually look at instead of the global namespace
     */
    protected $_routes;

    /**
     * @var  array   parameters from the route
     */
    public $_params = array();

    /**
     * @var  string  controller to be executed
     */
    protected $_controller;

    /**
     * @var  string  action to be executed in the controller
     */
    protected $_action;


    public function __construct($uri = null) {
        $this->init($uri);
    }


    abstract function execute();


    /**
     * Process a request to find a matching route
     *
     * @param   object  $request Request
     * @param   array   $routes  Route
     * @return  array
     */
    public function match_route($routes = NULL)
    {

        // Load routes
        $routes = (empty($routes)) ? Route::all() : $routes;

        $params = NULL;

        foreach ($routes as $name => $route)
        {
            /* @var $route Route */

            // We found something suitable
            if ($params = $route->matches($this))
            {
                return array(
                    'params' => $params,
                    'route' => $route,
                );
            }
        }

        return NULL;
    }


    /**
     * Sets and gets the controller for the matched route.
     *
     * @param   string   $controller  Controller to execute the action
     * @return  mixed
     */
    public function controller($controller = NULL)
    {
        if ($controller === NULL)
        {
            // Act as a getter
            return $this->_controller;
        }

        // Act as a setter
        $this->_controller = (string) $controller;

        return $this;
    }

    /**
     * Sets and gets the action for the controller.
     *
     * @param   string   $action  Action to execute the controller from
     * @return  mixed
     */
    public function action($action = NULL)
    {
        if ($action === NULL)
        {
            // Act as a getter
            return $this->_action;
        }

        // Act as a setter
        $this->_action = (string) $action;

        return $this;
    }


    /**
     * Sets and gets the params for the action.
     *
     * @param   array   $params
     * @return  mixed
     */
    public function params($params = NULL)
    {
        if ($params === NULL)
        {
            // Act as a getter
            return $this->_params;
        }

        // Act as a setter
        $this->_params = $params;

        return $this;
    }
}