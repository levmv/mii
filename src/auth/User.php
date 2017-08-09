<?php

namespace mii\auth;

use mii\db\DB;
use mii\db\ORM;
use mii\db\Query;
use Mii;

class User extends ORM {

    public function on_change() {
        if($this->changed('password')) {
            $this->password = Mii::$app->auth->hash($this->password);
        }
    }

    public function find_user($username) {
        return static::find()->where('username', '=', $username)->one();
    }

    public function complete_login() {

    }

    public function link_role($role_name) {

        $role = Role::find()->where('name', '=', $role_name)->one();

        if(! $role)
            return false;

        try {
            DB::insert('INSERT INTO `roles_users` VALUES(:user_id, :role_id)', [
                ':user_id' => $this->id,
                ':role_id' => $role->id
            ]);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }


    public function has_role($role_names) : bool
    {
        $roles = $this->get_roles();

        if(! is_array($role_names)) {
            $role_names = (array) $role_names;
        }

        foreach($role_names as $role_name) {
            foreach ($roles as $role) {
                if ($role === $role_name)
                    return true;
            }
        }

        return false;
    }

    public function update_roles($roles) {

        DB::delete('DELETE FROM roles_users WHERE user_id = :id', [':id' => $this->id]);

        foreach($roles as $role) {
            DB::insert('INSERT INTO roles_users VALUES(:user_id, :role_id)', [
                ':user_id' => $this->id,
                ':role_id' => $role
            ]);
        }
    }


    public function get_roles() {
        if(!$this->id)
            return [];

        $list = [];

        if($this->roles_cache AND !empty($this->roles_cache)) {
            $list = explode(':',$this->roles_cache);

        } else {
            $roles = Role::find()
                        ->join('roles_users')->on('roles_users.role_id','=','roles.id')
                        ->where('roles_users.user_id','=',$this->id)
                        ->all();


            foreach($roles as $item)
                $list[] = $item->name;

            if(!empty($list)) {
                $this->roles_cache = implode(':',$list);
                (new Query)
                    ->update($this->get_table())
                    ->set(['roles_cache' => $this->roles_cache])
                    ->where('id', '=', $this->id)
                    ->execute();
            }
        }

        return $list;
    }

    public function get_roles_desc() {
        static $all_roles = false;

        if(!$all_roles) {
            $roles = Role::all();

            foreach($roles as $item) {

                $all_roles[$item->name] = $item->title;
            }
        }


        $roles = $this->get_roles();


        $list = [];
        foreach($roles as $role_name) {
            if(isset($all_roles[$role_name]))
                $list[] = $all_roles[$role_name];
        }

        return $list;
    }



}