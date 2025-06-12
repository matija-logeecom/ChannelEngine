<?php

namespace ChannelEngine\Infrastructure\Session;

class SessionManager
{
    private static ?SessionManager $instance = null;

    private function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Gets the single instance of the SessionManager.
     *
     * @return SessionManager
     */
    public static function getInstance(): SessionManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Gets a value from the session.
     *
     * @param string $key The key of the item to retrieve.
     *
     * @return mixed The value from the session, or the default value.
     */
    public function get(string $key): mixed
    {
        return $_SESSION[$key];
    }

    /**
     * Sets a value in the session.
     *
     * @param string $key The key of the item to set.
     * @param mixed $value The value to set.
     *
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Checks if a key exists in the session.
     *
     * @param string $key The key to check.
     *
     * @return bool True if the key exists, false otherwise.
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }
}