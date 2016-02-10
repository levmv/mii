<?php

namespace mii\console;

use mii\core\Exception;

class CliException extends \mii\core\Exception {

    public function __construct($message = "", array $variables = NULL, $code = 0) {
        parent::__construct($message, $variables, $code);
    }

    public function get_name() {
        return 'Error';
    }

}
