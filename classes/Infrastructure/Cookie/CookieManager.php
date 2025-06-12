<?php

namespace ChannelEngine\Infrastructure\Cookie;

class CookieManager
{
    private static ?CookieManager $instance = null;

    /**
     * @return CookieManager
     */
    public static function getInstance(): CookieManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function get(string $name): mixed
    {
        return $_COOKIE[$name] ?? null;
    }

    /**
     * @param string $name
     * @param string $value
     * @param int $expiry
     * @param bool $secure
     *
     * @return void
     */
    public function set(string $name, string $value, int $expiry, bool $secure = false): void
    {
        setcookie($name, $value, [
            'expires' => $expiry,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * @param string $name
     * @param bool $secure
     *
     * @return void
     */
    public function setEmptyCookie(string $name, bool $secure = false): void
    {
        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}