<?php

namespace mii\valid;

use mii\core\Exception;

class ValidationException extends Exception
{

    public function get_name() {
        return 'Validation Error';
    }
}

;