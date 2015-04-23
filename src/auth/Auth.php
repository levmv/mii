<?php

namespace mii\auth;

use Mii;
use mii\db\Query;
use mii\web\Cookie;
use mii\web\Exception;
use mii\web\Session;

/**
 * User authorization library. Handles user login and logout, as well as secure
 * password hashing.
 *
 */
class Auth {

    // Auth instances
    protected static $_instance;

    /**
     * Singleton pattern
     *
     * @return Auth
     */
    public static function instance()
    {
        if ( ! isset(Auth::$_instance))
        {
            // Load the configuration for this type
            $config = Mii::$app->config('auth');

            static::$_instance = new Auth($config);
        }
        return static::$_instance;
    }

    protected $_session;

    protected $_config;

    protected $_user;


    /**
     * Loads Session and configuration options.
     *
     * @param   array  $config  Config Options
     * @return  void
     */
    public function __construct($config = [])
    {
        // Save the config in the object
        $this->_config = $config;

        $this->_session = Session::instance();
    }


    /**
     * Gets the currently logged in user from the session (with auto_login check).
     * Returns FALSE if no user is currently logged in.
     *
     * @return  mixed
     */
    public function get_user($default = NULL)
    {
        if($this->_user)
            return $this->_user;

        if($this->_session->check_cookie()) {
            $this->_user = $this->_session->get($this->_config['session_key'], $default);
        }

        if ( ! $this->_user AND Cookie::get($this->_config['token_cookie'], false))
        {
            // check for "remembered" login
            $this->auto_login();
        }

        return ($this->_user AND $this->_user->loaded()) ? $this->_user : $default;
    }

    public function get_user_model() {
        return new $this->_config['user_model'];
    }

    /**
     * Attempt to log in a user by using an ORM object and plain-text password.
     *
     * @param   string   $username  Username to log in
     * @param   string   $password  Password to check against
     * @param   boolean  $remember  Enable autologin
     * @return  boolean
     */
    public function login($username, $password, $remember = true)
    {
        if (empty($password))
            return false;

        $user = (new $this->_config['user_model'])->find_user($username);

        if(!$user)
            return false;

        if ($user->id AND $user->has_role('login') AND $this->verify_password($password, $user->password))
        {
            if ($remember === true)
            {
                // Token data
                $data = [
                    'user_id'    => $user->id,
                    'expires'    => time() + $this->_config['lifetime'],
                    'user_agent' => sha1(Mii::$app->request->get_user_agent()),
                ];

                // Create a new autologin token
                $token = (new Token)->create_token($data);

                // Set the autologin cookie
                Cookie::set($this->_config['token_cookie'], $token->token, $this->_config['lifetime']);
            }

            // Finish the login
            $this->complete_login($user);
            $this->_user = $user;

            return true;
        }

        // Login failed
        return false;
    }


    /**
     * Log a user out and remove any autologin cookies.
     *
     * @param   boolean  $destroy completely destroy the session
     * @param	boolean  $logout_all remove all tokens for user
     * @return  boolean
     */
    public function logout($destroy = FALSE, $logout_all = FALSE)
    {
        // Set by force_login()
        $this->_session->delete('auth_forced');


        if ($token = Cookie::get($this->_config['token_cookie']))
        {
            // Delete the autologin cookie to prevent re-login
            Cookie::delete($this->_config['token_cookie']);

            // Clear the autologin token from the database
            $token = (new Token)->get_token($token);

            if ($token AND $token->loaded() AND $logout_all)
            {
                (new Query)->delete($token->get_table())->where('user_id', '=', $token->user_id)->execute();
            }
            elseif ($token AND $token->loaded())
            {
                $token->delete();
            }
        }

        if ($destroy === TRUE)
        {
            // Destroy the session completely
            $this->_session->destroy();
        }
        else
        {
            // Remove the user from the session
            $this->_session->delete($this->_config['session_key']);

            // Regenerate session_id
            $this->_session->regenerate();
        }

        $this->_user = null;

        // Double check
        return ! $this->logged_in();
    }


    /**
     * Check if there is an active session. Optionally allows checking for a
     * specific role.
     *
     * @param   string  $role  role name
     * @return  boolean
     */
    public function logged_in($role = NULL)
    {
        // Get the user from the session
        $user = $this->get_user();


        if ($user AND is_object($user) AND $user->id AND $user->has_role('login')) {
            return true;
        }

        return false;
    }

    /**
     * Creates a hashed hmac password from a plaintext password. This
     * method is deprecated, [Auth::hash] should be used instead.
     *
     * @deprecated
     * @param  string  $password Plaintext password
     */
    public function hash_password($password)
    {
        return $this->hash($password);
    }


    /**
     *
     * Uses hash_hmac for legacy projects
     *
     * @param   string  $str  password to hash
     * @return  string
     */
    public function hash($password)
    {
        if(!isset($this->_config['hash_method']) OR $this->_config['hash_method'] == 'bcrypt') {

            $cost = isset($this->_config['hash_cost']) ? $this->_config['hash_cost'] : 9;

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);

        } else {
            if ( ! $this->_config['hash_key'])
                throw new Exception('A valid hash key must be set in your auth config.');

            $hash = hash_hmac($this->_config['hash_method'], $password, $this->_config['hash_key']);
        }

        return $hash;
    }


    public function verify_password($password, $hash) {
        if(!isset($this->_config['hash_method']) OR $this->_config['hash_method'] == 'bcrypt') {
            return password_verify($password, $hash);
        } else {

            return $this->hash($password) === $hash;
        }

    }


    protected function complete_login($user)
    {
        // Regenerate session_id
        $this->_session->regenerate();

        // Store user in session
        $this->_session->set($this->_config['session_key'], $user);

        $this->_user = $user;

        $user->complete_login();

        return true;
    }



    /**
     * Get the stored password for a username.
     *
     * @param   mixed   username string, or user Jelly object
     * @return  string
     */
    public function password($user)
    {
        if ( ! is_object($user))
        {
            $username = $user;

            // Load the user

            $user = Jelly::query('User')->where(Jelly::factory('User')->unique_key($username), '=', $username)->limit(1)->select();
        }

        return $user->password;
    }

    /**
     * Compare password with original (hashed). Works for current (logged in) user
     *
     * @param   string  $password
     * @return  boolean
     */
    public function check_password($password)
    {
        $user = $this->get_user();

        if ( ! $user)
            return FALSE;

        return ($this->hash($password) === $user->password);
    }


    /**
     * Forces a user to be logged in, without specifying a password.
     *
     * @param   mixed    $user username string, or user Jelly object
     * @param   boolean  $mark_session_as_forced mark the session as forced
     * @return  boolean
     */
    public function force_login($user, $mark_session_as_forced = FALSE)
    {
        if ( ! is_object($user))
        {
            $user = ORM::factory('User')->find($user);

            if(!$user)
                return false;
        }

        if ($mark_session_as_forced === TRUE)
        {
            // Mark the session as forced, to prevent users from changing account information
            $this->_session->set('auth_forced', TRUE);
        }

        // Run the standard completion
        $this->complete_login($user);
    }

    /**
     * Logs a user in, based on the token cookie.
     *
     * @return  mixed
     */
    public function auto_login()
    {
        if ($token = Cookie::get($this->_config['token_cookie']))
        {
            // Load the token and user
            $token = Token::find()->where('token', '=', $token)->one();

            if ($token)
            {
                if ($token->user_agent === sha1(Mii::$app->request->get_user_agent()))
                {
                    $new_token = (new Token)->create_token([
                        'user_id'    => $token->user_id,
                        'user_agent' => $token->user_agent,
                        'expires'    => time() + $this->_config['lifetime']
                    ]);
                    // Set the new token
                    Cookie::set($this->_config['token_cookie'], $new_token->token, $new_token->expires - time());

                    // Complete the login with the found data

                    $user = call_user_func([$this->_config['user_model'], 'find'], $new_token->user_id);

                    $this->complete_login($user);

                    $token->delete();

                    // Automatic login was successful
                    return $user;
                }

                // Token is invalid
                $token->delete();
            } else {

                // Token is invalid
                Cookie::delete($this->_config['token_cookie']);
            }
        }

        return false;
    }
}