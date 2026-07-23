<?php

declare(strict_types=1);

namespace App\Auth;

final class SessionManager
{
    public function start(string $name, bool $secure, string $cookiePath = '/'): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath === '' ? '/' : '/' . trim($cookiePath, '/') . '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function get(string$key,mixed$default=null):mixed{return$_SESSION[$key]??$default;}
    public function put(string$key,mixed$value):void{$_SESSION[$key]=$value;}
    public function remove(string$key):void{unset($_SESSION[$key]);}
}
