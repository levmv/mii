<?php

namespace mii\auth;

use mii\db\ORM;
use mii\db\Query;
use mii\util\Text;

class Token extends ORM
{
    protected static $table = 'user_tokens';

    protected $_data = [
        'id' => 0,
        'token' => '',
        'expires' => 0,
        'user_id' => 0,
    ];

    public function on_create() {
        $this->token = Text::base64url_encode(random_bytes(24));

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

    public static function delete_all_by_user($user_id) {
        return static::query()
            ->delete()
            ->where('user_id', '=', $user_id)
            ->execute();
    }


}