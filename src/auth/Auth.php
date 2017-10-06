<?php

namespace mii\auth;

use Mii;
use mii\core\Component;
use mii\db\Query;

/**
 * User authorization library. Handles user login and logout, as well as secure
 * password hashing.
 *
 */
class Auth extends Component
{
    protected $_session;

    protected $_user;

    protected $user_model = 'app\models\User';

    protected $hash_cost = 8;

    protected $lifetime = 2592000;

    protected $session_key = 'miiu';

    protected $token_cookie = 'miia';

    protected $hash_key;


    /**
     * Loads Session and configuration options.
     *
     * @param   array $config Config Options
     */
    public function init(array $config = []): void {
        parent::init($config);
        $this->_session = \Mii::$app->session;
    }


    /**
     * Gets the currently logged in user from the session (with auto_login check).
     * Returns FALSE if no user is currently logged in.
     *
     * @return  mixed
     */
    public function get_user(): ?User {
        if ($this->_user)
            return $this->_user;

        if ($this->_session->check_cookie()) {
            $this->_user = $this->_session->get($this->session_key);
        }

        if (!$this->_user AND Mii::$app->request->get_cookie($this->token_cookie, false)) {
            // check for "remembered" login
            $this->auto_login();
        }

        return $this->_user;
    }

    public function get_user_model() {
        return new $this->user_model;
    }

    /**
     * Attempt to log in a user by using an ORM object and plain-text password.
     *
     * @param   string $username Username to log in
     * @param   string $password Password to check against
     * @param   boolean $remember Enable autologin
     * @return  boolean
     */
    public function login($username, $password, $remember = true) {

        if (empty($password))
            return false;

        $username = mb_strtolower($username, Mii::$app->charset);

        $user = (new $this->user_model)->find_user($username);

        if (!$user)
            return false;


        if ($user->id AND $user->has_role('login') AND $this->verify_password($password, $user->password)) {
            if ($remember === true) {
                // Token data
                $data = [
                    'user_id' => $user->id,
                    'expires' => time() + $this->lifetime,
                    'user_agent' => sha1(Mii::$app->request->get_user_agent()),
                ];

                // Create a new autologin token
                $token = (new Token)->set($data);
                $token->create();

                // Set the autologin cookie
                Mii::$app->request->set_cookie($this->token_cookie, $token->token, $this->lifetime);
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
     * @param   boolean $destroy completely destroy the session
     * @param    boolean $logout_all remove all tokens for user
     * @return  boolean
     */
    public function logout($destroy = false, $logout_all = false) {
        // Set by force_login()
        $this->_session->delete('auth_forced');


        if ($token = Mii::$app->request->get_cookie($this->token_cookie)) {
            // Delete the autologin cookie to prevent re-login
            Mii::$app->request->delete_cookie($this->token_cookie);

            // Clear the autologin token from the database
            $token = (new Token)->get_token($token);

            if ($token AND $token->loaded() AND $logout_all) {
                (new Query)->delete($token->get_table())->where('user_id', '=', $token->user_id)->execute();
            } elseif ($token AND $token->loaded()) {
                $token->delete();
            }
        }

        if ($destroy === true) {
            // Destroy the session completely
            $this->_session->destroy();
        } else {
            // Remove the user from the session
            $this->_session->delete($this->session_key);

            // Regenerate session_id
            $this->_session->regenerate();
        }

        $this->_user = null;

        // Double check
        return !$this->logged_in();
    }


    /**
     * Check if there is an active session. Optionally allows checking for a
     * specific role. By default checking for «login» role.
     */
    public function logged_in($role = 'login'): bool {
        // Get the user from the session
        $user = $this->get_user();


        return $user AND is_object($user) AND $user->id AND
            ($role !== null ? $user->has_role($role) : true);
    }


    /**
     *
     * @param   string $password password to hash
     * @return  string
     */
    public function hash(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->hash_cost]);
    }


    public function verify_password($password, $hash) {
        return password_verify($password, $hash);
    }


    protected function complete_login($user) {
        // Regenerate session_id
        $this->_session->regenerate();

        // Store user in session
        $this->_session->set($this->session_key, $user);

        $this->_user = $user;

        $user->complete_login();

        return true;
    }


    /**
     * Compare password with original (hashed). Works for current (logged in) user
     *
     * @param   string $password
     * @return  boolean
     */
    public function check_password($password) {
        $user = $this->get_user();

        if (!$user)
            return false;

        return ($this->hash($password) === $user->password);
    }


    /**
     * Forces a user to be logged in, without specifying a password.
     *
     * @param   User $user
     * @param   boolean $mark_session_as_forced mark the session as forced
     * @return  boolean
     */
    public function force_login(User $user, $mark_session_as_forced = false) {

        if ($mark_session_as_forced === true) {
            // Mark the session as forced, to prevent users from changing account information
            $this->_session->set('auth_forced', true);
        }

        // Run the standard completion
        $this->complete_login($user);

        return true;
    }

    /**
     * Logs a user in, based on the token cookie.
     *
     * @return  mixed
     */
    public function auto_login(): ?User {
        if ($token = Mii::$app->request->get_cookie($this->token_cookie)) {
            // Load the token and user
            $token = Token::one([
                ['token', '=', $token]
            ]);

            if ($token !== null) {

                if ($token->user_agent === sha1(Mii::$app->request->get_user_agent())) {
                    $new_token = (new Token)->set([
                        'user_id' => $token->user_id,
                        'user_agent' => $token->user_agent,
                        'expires' => time() + $this->lifetime
                    ]);
                    $new_token->create();

                    // Set the new token
                    Mii::$app->request->set_cookie($this->token_cookie, $new_token->token, $new_token->expires - time());

                    // Complete the login with the found data

                    $user = call_user_func([$this->user_model, 'one'], $new_token->user_id);

                    $this->complete_login($user);

                    $token->delete();

                    // Automatic login was successful
                    return $user;
                }

                // Token is invalid
                if ($token->loaded())
                    $token->delete();
            } else {

                // Token is invalid
                Mii::$app->request->delete_cookie($this->token_cookie);
            }
        }

        return null;
    }
}