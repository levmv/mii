<?php

namespace mii\web;


class Session
{

    /**
     * @var  array  session instances
     */
    public static $_instance = NULL;
    /**
     * @var  string  cookie name
     */
    protected $_name = 'session';
    /**
     * @var  int  cookie lifetime
     */
    protected $_lifetime = 0;
    /**
     * @var  bool  encrypt session data?
     */
    protected $_encrypted = false;
    /**
     * @var  array  session data
     */
    protected $_data = [];
    /**
     * @var  bool  session destroyed?
     */
    protected $_destroyed = false;

    protected $_flash = '__flash';

    /**
     * Overloads the name, lifetime, and encrypted session settings.
     *
     * [!!] Sessions can only be created using the [Session::instance] method.
     *
     * @param   array $config configuration
     * @param   string $id session id
     * @return  void
     * @uses    Session::read
     */
    public function __construct(array $config = [])
    {

        if (isset($config['name'])) {
            // Cookie name to store the session id in
            $this->_name = (string)$config['name'];
        }

        if (isset($config['lifetime'])) {
            // Cookie lifetime
            $this->_lifetime = (int)$config['lifetime'];
        }

        if (isset($config['encrypted'])) {
            if ($config['encrypted'] === true) {
                // Use the default Encrypt instance
                $config['encrypted'] = 'default';
            }

            // Enable or disable encryption of data
            $this->_encrypted = $config['encrypted'];
        }

        // Load the session
        //$this->read($id);
    }


    /**
     *
     * Checks for session cookie without starting session itself
     *
     * @return bool
     */

    public function check_cookie()
    {
        return isset($_COOKIE[$this->_name]);
    }

    /**
     * Get the current session cookie name.
     *
     *     $name = $session->name();
     *
     * @return  string
     */
    public function name()
    {
        return $this->_name;
    }

    /**
     * Get a variable from the session array.
     *
     *     $foo = $session->get('foo');
     *
     * @param   string $key variable name
     * @param   mixed $default default value to return
     * @return  mixed
     */
    public function get($key, $default = null)
    {
        $this->open();

        return array_key_exists($key, $this->_data) ? $this->_data[$key] : $default;
    }


    public function has($key)
    {
        $this->open();

        return array_key_exists($key, $this->_data);
    }


    public function open($id = null)
    {

        if ($this->is_active())
            return;

        // Sync up the session cookie with Cookie parameters
        session_set_cookie_params($this->_lifetime,
            \Mii::$app->request->cookie_path,
            \Mii::$app->request->cookie_domain,
            \Mii::$app->request->cookie_secure,
            \Mii::$app->request->cookie_httponly);

        // Do not allow PHP to send Cache-Control headers
        session_cache_limiter(false);

        // Set the session cookie name
        session_name($this->_name);

        if ($id) {
            // Set the session id
            session_id($id);
        }

        // Start the session
        @session_start();

        // Use the $_SESSION global for storing data
        $this->_data =& $_SESSION;

        // Write the session at shutdown
        register_shutdown_function([$this, 'close']);

        $this->update_flash_counters();

        return;
    }

    public function is_active()
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Set a variable in the session array.
     *
     *     $session->set('foo', 'bar');
     *
     * @param   string $key variable name
     * @param   mixed $value value
     * @return  $this
     */
    public function set($key, $value)
    {
        $this->open();

        $this->_data[$key] = $value;

        return $this;
    }

    /**
     * Set a variable by reference.
     *
     *     $session->bind('foo', $foo);
     *
     * @param   string $key variable name
     * @param   mixed $value referenced value
     * @return  $this
     */
    public function bind($key, & $value)
    {
        $this->open();

        $this->_data[$key] =& $value;

        return $this;
    }

    /**
     * Removes a variable in the session array.
     *
     *     $session->delete('foo');
     *
     * @param   string $key,... variable name
     * @return  $this
     */
    public function delete(...$args)
    {
        $this->open();

        foreach ($args as $key) {
            unset($this->_data[$key]);
        }

        return $this;
    }

    /**
     * Generates a new session id and returns it.
     *
     *     $id = $session->regenerate();
     *
     * @return  string
     */
    public function regenerate($delete_old = false)
    {
        if($this->is_active()) {
            // Regenerate the session id
            @session_regenerate_id($delete_old);
        } else {
            $this->open();
        }

        return session_id();
    }

    /**
     * Sets the last_active timestamp and saves the session.
     *
     *     $session->close();
     *
     * [!!] Any errors that occur during session writing will be logged,
     * but not displayed, because sessions are written after output has
     * been sent.
     *
     * @return  boolean
     */
    public function close()
    {
        if ($this->is_active()) {

            // Set the last active timestamp
            $this->_data['last_active'] = time();

            // Write and close the session
            @session_write_close();
        }
    }

    /**
     * Restart the session.
     *
     *     $success = $session->restart();
     *
     * @return  boolean
     */
    public function restart()
    {
        if ($this->is_active()) {

            // Wipe out the current session.
            $this->destroy();
        }

        // Fire up a new session
        //$status = session_start();
        $this->open();

        // Use the $_SESSION global for storing data
    //    $this->_data =& $_SESSION;

        return ;//$status;
    }

    /**
     * Completely destroy the current session.
     *
     *     $success = $session->destroy();
     *
     * @return  boolean
     */
    public function destroy()
    {
        if ($this->is_active()) {

            session_destroy();
            $this->_data = [];

            // Make sure the session cannot be restarted
            Cookie::delete($this->_name);
        }
    }

    /**
     * @return  string
     */
    public function id()
    {
        return session_id();
    }


    public function flash($key, $value = true)
    {
        $counters = $this->get($this->_flash, []);
        $counters[$key] = 0;
        $this->_data[$key] = $value;
        $this->_data[$this->_flash] = $counters;
    }


    private function update_flash_counters() {
        $counters = $this->get($this->_flash, []);
        if (is_array($counters)) {
            foreach ($counters as $key => $count) {
                if ($count > 0) {
                    unset($counters[$key], $_SESSION[$key]);
                } elseif ($count == 0) {
                    $counters[$key]++;
                }
            }
            $this->_data[$this->_flash] = $counters;
        } else {
            unset($this->_data[$this->_flash]);
        }
    }


}
