<?php

namespace mii\core;

class ACL
{

    /**
     * @var array ACL rules
     */
    public $_rules = [];


    /**
     * Add "allow" access to a role or roles.
     *
     * @param mixed $role single role or array of roles
     * @param string $object
     * @param string $action
     * @return $this
     */
    public function allow($role, $object = '*', $action = '*')
    {
        $this->add_rule(true, $role, $object, $action);

        return $this;
    }

    /**
     * Add "deny" access to a role or roles.
     *
     * @param mixed $role single role or array of roles
     * @param string $object
     * @param string $action
     * @return $this
     */
    public function deny($role, $object = '*', $action = '*')
    {
        $this->add_rule(false, $role, $object, $action);

        return $this;
    }

    /**
     * Clear array of ACL rules.
     *
     * @return $this
     */
    public function clear()
    {
        $this->_rules = [];

        return $this;
    }

    /**
     * Add rule to array of ACL rules.
     *
     * @param bool $access
     * @param mixed $role single role or array of roles
     * @param string $object
     * @param string $action
     */
    public function add_rule($access, $role, $object, $action) : void
    {
        $roles = (array) $role;

        foreach ($roles as $r) {
            $this->_rules[$r][$object][$action] = $access;
        }
    }

    /**
     * Check if a role (or one of roles) is allowed to an action on an object.
     *
     * @param mixed $role single role or array of roles
     * @param string $object
     * @param string $action
     * @return bool
     */
    public function check($role, $object = '*', $action = '*') : bool
    {
        if (is_array($role)) {
            foreach ($role as $r) {
                if ($this->match($r, $object, $action))
                    return true;
            }

            return false;
        }

        return $this->match($role, $object, $action);
    }

    /**
     * Check if a role is allowed to an action on an object.
     *
     * @param $role
     * @param $object
     * @param $action
     * @return bool
     */
    protected function match($role, $object, $action) : bool
    {
        $roles = $objects = $actions = ['*'];

        $allow = false;

        if ($role != '*')
            array_unshift($roles, $role);
        if ($object != '*')
            array_unshift($objects, $object);
        if ($action != '*')
            array_unshift($actions, $action);

        // Ищем наиболее подходящее правило. Идем от частного к общему.
        foreach ($roles as $_role) {
            foreach ($objects as $_object) {
                foreach ($actions as $_action) {
                    if (isset($this->_rules[$_role][$_object][$_action])) {
                        $allow = $this->_rules[$_role][$_object][$_action];
                        break 3;
                    }
                }
            }
        }

        return $allow === true;
    }
}