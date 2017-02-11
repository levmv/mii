<?php

namespace mii\auth;

use mii\db\ORM;
use mii\db\Query;

class Token extends ORM
{
    protected static $table = 'user_tokens';

    protected $_order_by = ['expires' => 'asc'];

    protected $_data = [
        'id' => 0,
        'user_agent' => '',
        'token' => '',
        'type' => '',
        'created' => 0, // auto_now_create
        'expires' => 0,
        'user_id' => 0,
    ];

    public function on_create() {
        $this->created = time();

        do {
            // TODO: change to random_bytes() when php 7.0 will come
            $this->token = bin2hex(random_bytes(20));
        } while ($this->get_token($this->token) !== null);

        if (mt_rand(1, 100) === 1) {
            // Do garbage collection
            $this->delete_expired();
        }
    }

    /**
     * Deletes all expired tokens.
     *
     */
    public function delete_expired() {
        (new Query)
            ->delete($this->get_table())
            ->where('expires', '<', time())
            ->execute();

        return $this;
    }


    /**
     * Loads a token.
     *
     * @param    string $token
     * @return    Token
     * @return    null
     */
    public function get_token($token) {
        return static::find()->where('token', '=', $token)->one();
    }

}