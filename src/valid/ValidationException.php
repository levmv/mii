<?php

namespace mii\valid;

class ValidationException extends \mii\core\Exception {

    public function get_name() {
        return 'Validation Error';
    }
};