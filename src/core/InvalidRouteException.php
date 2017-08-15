<?php

namespace mii\core;


class InvalidRouteException extends Exception
{

    public function get_name() {
        return 'Invalid route';
    }
}