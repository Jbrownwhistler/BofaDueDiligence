<?php
class AuthController {
    public function login(): void {
        // Already logged in?
        if (Auth::check()) {
            Auth::redirectToDashboard();
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Veuillez remplir tous les champs.';
            } elseif (Auth::login($email, $password)) {
                Auth::redirectToDashboard();
            } else {
                $error = 'Identifiants incorrects ou compte désactivé.';
            }
        }

        include __DIR__ . '/../../templates/auth/login.php';
    }

    public function logout(): void {
        Auth::logout();
        header('Location: ' . BASE_URL . 'login');
        exit;
    }
}
