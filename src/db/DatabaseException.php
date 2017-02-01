<?php

namespace mii\db;


use mii\core\Exception;

class DatabaseException extends Exception {

    public function get_name() {
        return 'Database Exception';
    }

};