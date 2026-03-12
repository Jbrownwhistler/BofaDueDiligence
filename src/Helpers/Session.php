<?php
class Session {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'httponly'  => true,
                'samesite'  => 'Strict',
            ]);
            session_start();
        }
    }

    public static function set(string $key, mixed $value): void {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void {
        session_unset();
        session_destroy();
    }

    public static function regenerate(): void {
        session_regenerate_id(true);
    }

    public static function setFlash(string $type, string $message): void {
        $_SESSION['flash'][$type] = $message;
    }

    public static function getFlash(string $type): ?string {
        $msg = $_SESSION['flash'][$type] ?? null;
        unset($_SESSION['flash'][$type]);
        return $msg;
    }

    public static function hasFlash(string $type): bool {
        return isset($_SESSION['flash'][$type]);
    }
}
