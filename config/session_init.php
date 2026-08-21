<?php
// Centralized session bootstrap — include this everywhere instead of calling
// session_start() directly, so every entry point agrees on the same lifetime.
// (Mixing ini_set values across entry points causes PHP's session GC to reap
// sessions early using whichever entry point's setting was active when GC ran.)

if (!defined('AUTH_SESSION_LIFETIME')) {
    define('AUTH_SESSION_LIFETIME', 28800); // 8 hours
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', AUTH_SESSION_LIFETIME);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 1);

    $authCookieSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => AUTH_SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $authCookieSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
