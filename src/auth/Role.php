<?php

namespace mii\auth;

use mii\db\ORM;

class Role extends ORM
{

    protected static $table = 'roles';

    protected $_data = [
        'id' => 0,
        'name' => '',
        'title' => '',
        'desc' => ''
    ];


    public function users() {

        $class = \Mii::$app->auth->get_user_model();

        return (new $class)
            ->select_query()
            ->join('roles_users')
            ->on('roles_users.user_id', '=', 'users.id')
            ->where('roles_users.role_id', '=', $this->id);

    }


}