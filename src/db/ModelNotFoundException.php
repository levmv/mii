<?php

namespace mii\db;


class ModelNotFoundException extends DatabaseException
{

    // todo

    public function get_name() {
        return 'Model not found';
    }

}