<?php

namespace mii\auth;

use mii\db\ORM;

class Role extends ORM {

    protected $table = 'roles';

    protected $_data = [
        'id' => 0,
        'name' => '',
        'title' => '',
        'desc' => ''
    ];

    /**
     * Returns the ids of available roles.
     *
     * @param	array  $role
     * @return	array
     */
    public function get_role_ids(array $role)
    {
        return ORM::factory('Role')
            ->load(
                DB::query('id')
                    ->where('name', 'IN', $role)
            )->as_array();

    }

    /**
     * Loads a role based on name.
     *
     * @param	string	$role
     * @return	Jelly_Model
     */
    public function get_role($role)
    {
        return Jelly::query('role')->where('name', '=', $role)->limit(1)->select();
    }

    public function users() {

        //$user = \Mii::$app->auth()->get_user_model();


        return User::find()
                    ->join('roles_users')
                    ->on('roles_users.user_id', '=', 'users.id')
                    ->where('roles_users.role_id', '=', $this->id);

    }



}