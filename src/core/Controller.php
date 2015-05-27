<?php

namespace mii\core;

abstract class Controller
{

    public $auto_render = true;




    /**
     * Issues a HTTP redirect.
     *
     * Proxies to the [HTTP::redirect] method.
     *
     * @param  int $code HTTP Status code to use for the redirect
     * @param  string $uri URI to redirect to
     * @throws HTTP_Exception
     */
    public static function redirect($code = 302, $uri = '')
    {
        return HTTP::redirect($uri, $code);
    }


    /**
     * Executes the given action and calls the [Controller::before] and [Controller::after] methods.
     *
     * Can also be used to catch exceptions from actions in a single place.
     *
     * 1. Before the controller action is called, the [Controller::before] method
     * will be called.
     * 2. Next the controller action will be called.
     * 3. After the controller action is called, the [Controller::after] method
     * will be called.
     *
     * @throws  HTTP_Exception_404
     * @return  Response
     */
    public function execute($params = [])
    {

    }

    protected function before()
    {

        return true;
    }



    protected function after()
    {
        return $this->response;
    }

    /**
     * Checks the browser cache to see the response needs to be returned,
     * execution will halt and a 304 Not Modified will be sent if the
     * browser cache is up to date.
     *
     *     $this->check_cache(sha1($content));
     *
     * @param  string $etag Resource Etag
     * @return Response
     */
    protected function check_cache($etag = NULL)
    {
        return HTTP::check_cache($this->request, $this->response, $etag);
    }

}