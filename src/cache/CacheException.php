<?php

namespace mii\cache;

use mii\core\Exception;

class CacheException extends Exception {

    public function get_name() {
        return 'Cache error';
    }

};