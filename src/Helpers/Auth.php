<?php
class Auth {
    public static function login(string $email, string $password): bool {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND statut = ?');
        $stmt->execute([$email, 'actif']);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            Session::regenerate();
            Session::set('user_id', $user['id']);
            Session::set('user_role', $user['role']);
            Session::set('user_name', $user['prenom'] . ' ' . $user['nom']);
            Session::set('user_email', $user['email']);

            // Update last login
            $db->prepare('UPDATE users SET dernier_login = NOW() WHERE id = ?')->execute([$user['id']]);

            AuditLog::log('Connexion réussie', 'users', $user['id']);
            return true;
        }
        return false;
    }

    public static function logout(): void {
        if (self::check()) {
            AuditLog::log('Déconnexion', 'users', self::id());
        }
        Session::destroy();
    }

    public static function check(): bool {
        return Session::has('user_id');
    }

    public static function id(): ?int {
        return Session::get('user_id');
    }

    public static function role(): ?string {
        return Session::get('user_role');
    }

    public static function name(): ?string {
        return Session::get('user_name');
    }

    public static function isAdmin(): bool {
        return self::role() === 'admin';
    }

    public static function isAgent(): bool {
        return self::role() === 'agent';
    }

    public static function isClient(): bool {
        return self::role() === 'client';
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            header('Location: ' . BASE_URL . 'login');
            exit;
        }
    }

    public static function requireRole(string $role): void {
        self::requireLogin();
        // Admin can access agent routes too
        if ($role === 'agent' && self::isAdmin()) return;
        if (self::role() !== $role) {
            http_response_code(403);
            die('Accès interdit.');
        }
    }

    public static function redirectToDashboard(): void {
        $role = self::role();
        $url = match($role) {
            'admin'  => BASE_URL . 'admin/dashboard',
            'agent'  => BASE_URL . 'agent/dashboard',
            'client' => BASE_URL . 'client/dashboard',
            default  => BASE_URL . 'login',
        };
        header('Location: ' . $url);
        exit;
    }
}
