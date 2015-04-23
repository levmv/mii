<?php

namespace mii\auth;

use mii\db\ORM;
use mii\db\Query;
use mii\util\Text;

class Token extends ORM
{
    protected $table= 'user_tokens';

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

        if (mt_rand(1, 100) === 1)
        {
            // Do garbage collection
            $this->delete_expired();
        }

        /*if ($this->expires < time() AND $this->loaded())
        {
            // This object has expired
            $this->delete();
        }
*/
    }

    /**
     * Deletes all expired tokens.
     *
     */
    public function delete_expired()
    {
        // Delete all expired tokens
        (new Query)
            ->delete($this->get_table_name())
            ->where('expires', '<', time())
            ->execute();

        return $this;
    }

    /**
     * Creates a new token.
     *
     * @param	array  $data
     * @return	Token
     */
    public function create_token(array $data)
    {
        // Create the token
        do
        {
            // Todo: remove Text out of here.
            $token = sha1(uniqid(Text::random('alnum', 32), TRUE));
        }
        while ($this->get_token($token) !== null);

        // Store token in database
        return $this->set([
            'user_id'	 => $data['user_id'],
            'expires'	 => $data['expires'],
            'user_agent' => $data['user_agent'],
            'token'		 => $token,
        ])->create();

    }

    /**
     * Loads a token.
     *
     * @param	string	$token
     * @return	Token
     * @return	null
     */
    public function get_token($token)
    {
        return static::find()->where('token', '=', $token)->one();
    }

} // End Auth User Token Model