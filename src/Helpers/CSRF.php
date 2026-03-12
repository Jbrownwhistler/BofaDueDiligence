<?php
class CSRF {
    public static function generateToken(): string {
        if (!Session::has('csrf_token')) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return Session::get('csrf_token');
    }

    public static function field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::generateToken()) . '">';
    }

    public static function verify(?string $token): bool {
        if ($token === null) return false;
        return hash_equals(Session::get('csrf_token', ''), $token);
    }

    public static function check(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!self::verify($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                die('Erreur de sécurité : jeton CSRF invalide.');
            }
        }
    }
}
