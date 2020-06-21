<?php declare(strict_types=1);

namespace mii\core;

class ACL
{

    /**
     * @var array ACL rules
     */
    private array $_rules = [];


    /**
     * Add "allow" access to a role or roles.
     *
     * @param mixed  $role single role or array of roles
     * @param string $action
     * @return $this
     */
    public function allow($role, $action = '*'): self
    {
        $this->addRule(true, $role, $action);

        return $this;
    }

    /**
     * Add "deny" access to a role or roles.
     *
     * @param mixed  $role single role or array of roles
     * @param string $action
     * @return $this
     */
    public function deny($role, $action = '*'): self
    {
        $this->addRule(false, $role, $action);

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
     * @param bool   $access
     * @param mixed  $role single role or array of roles
     * @param string $action
     */
    public function addRule($access, $role, $action): void
    {
        $roles = (array) $role;

        foreach ($roles as $r) {
            $action = (array) $action;
            foreach ($action as $a) {
                $this->_rules[$r][$a] = $access;
            }
        }
    }

    /**
     * Check if a role (or one of roles) is allowed to an action.
     *
     * @param mixed  $role single role or array of roles
     * @param string $action
     * @return bool
     */
    public function check($role, $action = '*'): bool
    {
        if (\is_array($role)) {
            foreach ($role as $r) {
                if ($this->match($r, $action)) {
                    return true;
                }
            }

            return false;
        }

        return $this->match($role, $action);
    }

    /**
     * Check if a role is allowed to an action.
     *
     * @param $role
     * @param $action
     * @return bool
     */
    protected function match($role, $action): bool
    {
        $roles = $actions = ['*'];

        $allow = false;

        if ($role !== '*') {
            \array_unshift($roles, $role);
        }
        if ($action !== '*') {
            \array_unshift($actions, $action);
        }

        // Ищем наиболее подходящее правило. Идем от частного к общему.
        foreach ($roles as $_role) {
            foreach ($actions as $_action) {
                if (isset($this->_rules[$_role][$_action])) {
                    $allow = $this->_rules[$_role][$_action];
                    break 2;
                }
            }
        }

        return $allow === true;
    }
}
